<?php
/**
 * Lê o typicalLearningTime de cada XML LOM e salva em minutos
 * na tabela mdl_local_scorm_lom (criada pelo plugin local_scorm_lom).
 *
 * Uso:
 *   sudo php /home/luiza/Desktop/TCC/popular_tempo_estimado.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/public/config.php');

global $DB, $USER;
$USER = get_admin();

define('COURSE_ID', 2);
define('LOM_BASE_DIR', '/home/luiza/Desktop/TCC');

$disciplinas = [
    'Algorithm',
    'Computer Network',
    'Database And Software Engineering',
    'History of Computation And Computer History',
    'Numeric System And Logic',
    'Operation System And Computer Organization',
];

// ── Garante que o plugin está instalado ──────────────────────────────────────
if (!$DB->get_manager()->table_exists('local_scorm_lom')) {
    die("ERRO: tabela 'local_scorm_lom' não encontrada.\n"
      . "Instale o plugin primeiro:\n"
      . "  sudo cp -r /home/luiza/Desktop/TCC/plugin_scorm_lom /var/www/html/moodle/public/local/scorm_lom\n"
      . "  sudo chown -R www-data:www-data /var/www/html/moodle/public/local/scorm_lom\n"
      . "  sudo php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive\n");
}

// ── Converte duração ISO 8601 (PT1H30M20S) em minutos arredondados ───────────
function iso8601_para_minutos(string $dur): ?int {
    if (!preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $dur, $m)) {
        return null;
    }
    $segundos = (int)($m[1] ?? 0) * 3600
              + (int)($m[2] ?? 0) * 60
              + (int)($m[3] ?? 0);
    return (int)round($segundos / 60);
}

// ── Lê título e duração do XML LOM ───────────────────────────────────────────
function parse_lom_tempo(string $caminho): ?array {
    $xml = @simplexml_load_file($caminho);
    if (!$xml) return null;

    $xml->registerXPathNamespace('lom', 'http://ltsc.ieee.org/xsd/LOM');

    $titulo_arr = $xml->xpath('//lom:general/lom:title/lom:string');
    $titulo = $titulo_arr ? trim((string)$titulo_arr[0]) : '';

    $dur_arr = $xml->xpath('//lom:educational/lom:typicalLearningTime/lom:duration');
    $duracao_iso = $dur_arr ? trim((string)$dur_arr[0]) : '';

    return ['titulo' => $titulo, 'duracao_iso' => $duracao_iso];
}

// ── Carrega mapa título → cmid para todos os SCORMs do curso ─────────────────
$scorm_module_id = $DB->get_field('modules', 'id', ['name' => 'scorm']);
$rows = $DB->get_records_sql(
    "SELECT s.name, cm.id AS cmid
     FROM {scorm} s
     JOIN {course_modules} cm ON cm.instance = s.id AND cm.module = :mod
     WHERE s.course = :course",
    ['mod' => $scorm_module_id, 'course' => COURSE_ID]
);
$mapa_scorm = [];
foreach ($rows as $r) {
    $mapa_scorm[$r->name] = (int)$r->cmid;
}
echo "SCORMs carregados: " . count($mapa_scorm) . "\n\n";

// ── Execução principal ────────────────────────────────────────────────────────
$total_ok      = 0;
$total_pulados = 0;
$total_erros   = 0;

foreach ($disciplinas as $disciplina) {
    $pasta = LOM_BASE_DIR . '/' . $disciplina;
    if (!is_dir($pasta)) {
        echo "[AVISO] Pasta não encontrada: {$pasta}\n";
        continue;
    }

    echo "══ {$disciplina} ══\n";

    $xmls = glob($pasta . '/*.xml');
    sort($xmls);

    foreach ($xmls as $xml_path) {
        $lom = parse_lom_tempo($xml_path);
        if (!$lom) {
            echo "  [ERRO] Falha ao ler: " . basename($xml_path) . "\n";
            $total_erros++;
            continue;
        }

        if (empty($lom['duracao_iso'])) {
            echo "  [SEM DURAÇÃO] " . basename($xml_path) . " titulo='{$lom['titulo']}'\n";
            $total_pulados++;
            continue;
        }

        $minutos = iso8601_para_minutos($lom['duracao_iso']);
        if ($minutos === null) {
            echo "  [FORMATO INVÁLIDO] {$lom['duracao_iso']} em " . basename($xml_path) . "\n";
            $total_erros++;
            continue;
        }

        $cmid = $mapa_scorm[$lom['titulo']] ?? null;
        if (!$cmid) {
            echo "  [SEM SCORM] titulo='{$lom['titulo']}'\n";
            $total_erros++;
            continue;
        }

        $existente = $DB->get_record('local_scorm_lom', ['cmid' => $cmid]);
        if ($existente) {
            $existente->estimated_time = $minutos;
            $DB->update_record('local_scorm_lom', $existente);
        } else {
            $rec = new stdClass();
            $rec->cmid           = $cmid;
            $rec->estimated_time = $minutos;
            $DB->insert_record('local_scorm_lom', $rec);
        }

        echo "  [OK] cmid={$cmid} '{$lom['titulo']}' → {$minutos} min ({$lom['duracao_iso']})\n";
        $total_ok++;
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "Salvos: {$total_ok} | Sem duração: {$total_pulados} | Erros: {$total_erros}\n";
