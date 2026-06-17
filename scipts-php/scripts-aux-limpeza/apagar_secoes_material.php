<?php
/**
 * Apaga seções vazias com nome "Material *" ou "Materiais" do Moodle.
 * Só remove seções que não têm nenhuma atividade (sequence vazia).
 *
 * Uso:
 *   sudo php /home/luiza/Desktop/TCC/apagar_secoes_material.php
 *
 * Para apenas listar sem apagar, rode com --dry-run:
 *   sudo php /home/luiza/Desktop/TCC/apagar_secoes_material.php --dry-run
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');

global $DB, $USER;
$USER = get_admin();

$dry_run = in_array('--dry-run', $argv ?? []);

if ($dry_run) {
    echo "=== MODO DRY-RUN — nenhuma alteração será feita ===\n\n";
}

// Busca seções cujo nome é "Materiais" ou começa com "Material "
$secoes = $DB->get_records_sql(
    "SELECT id, course, section, name, sequence
     FROM {course_sections}
     WHERE name = 'Materiais' OR name LIKE 'Material %'
     ORDER BY course, section"
) ?: [];

if (empty($secoes)) {
    echo "Nenhuma seção com nome 'Material*' encontrada.\n";
    exit(0);
}

$apagadas        = 0;
$ignoradas       = 0;
$cursos_afetados = [];

foreach ($secoes as $s) {
    $vazia = empty(trim($s->sequence));

    if (!$vazia) {
        echo "[IGNORADA] id={$s->id} curso={$s->course} section={$s->section} nome='{$s->name}' — tem atividades (sequence={$s->sequence})\n";
        $ignoradas++;
        continue;
    }

    echo "[APAGAR] id={$s->id} curso={$s->course} section={$s->section} nome='{$s->name}'\n";

    if (!$dry_run) {
        $DB->delete_records('course_sections', ['id' => $s->id]);
        $cursos_afetados[$s->course] = true;
        $apagadas++;
    }
}

// Reconstrói cache dos cursos afetados
if (!$dry_run && !empty($cursos_afetados)) {
    foreach (array_keys($cursos_afetados) as $courseid) {
        rebuild_course_cache($courseid, true);
        echo "\nCache do curso {$courseid} reconstruído.\n";
    }
}

echo "\n═══════════════════════════════════════════════════════\n";
if ($dry_run) {
    $seriam = count(array_filter($secoes, fn($s) => empty(trim($s->sequence))));
    echo "Dry-run — seriam apagadas: {$seriam} | Ignoradas (com atividades): {$ignoradas}\n";
} else {
    echo "Apagadas: {$apagadas} | Ignoradas (com atividades): {$ignoradas}\n";
}
