<?php
/**
 * Associa cada SCORM do curso 2 à tag de dificuldade correspondente,
 * lendo o campo <difficulty> do imsmanifest.xml embutido no pacote SCORM.
 *
 * As 5 tags devem existir previamente no Moodle (criadas pelo admin).
 * O script usa core_tag_tag::set_item_tags() — API nativa do Moodle.
 *
 * Uso:
 *   sudo php /home/luiza/Desktop/TCC/associar_scorm_tags_dificuldade.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/public/config.php');

global $DB, $CFG, $USER;
$USER = get_admin();

define('COURSE_ID', 2);
define('LOM_BASE_DIR', '/home/luiza/Desktop/TCC');

// Mapeamento valor LOM → nome exato da tag criada no Moodle
$mapa_dificuldade = [
    'very easy'      => 'Very Easy',
    'easy'           => 'Easy',
    'medium'         => 'Medium',
    'difficult'      => 'Difficult',
    'very difficult' => 'Very Difficult',
];

// Pastas de disciplina (mesmo mapeamento dos outros scripts)
$disciplinas = [
    'Algorithm',
    'Computer Network',
    'Database And Software Engineering',
    'History of Computation And Computer History',
    'Numeric System And Logic',
    'Operation System And Computer Organization',
];

// ── Lê a dificuldade direto do XML LOM (mesmo parse do criar_scorms_lom.php) ──
function ler_dificuldade_lom(string $caminho): string {
    $xml = @simplexml_load_file($caminho);
    if (!$xml) return '';

    $xml->registerXPathNamespace('lom', 'http://ltsc.ieee.org/xsd/LOM');
    $vals = $xml->xpath('//lom:educational/lom:difficulty/lom:value');
    if (!$vals) return '';

    // Pega a última ocorrência (alguns XMLs têm duas entradas)
    return strtolower(trim((string)end($vals)));
}

// ── Carrega mapa título → cmid para todos os SCORMs do curso ──────────────────
$scorm_module_id = $DB->get_field('modules', 'id', ['name' => 'scorm']);
$rows = $DB->get_records_sql(
    "SELECT s.name, cm.id AS cmid, s.id AS scormid
     FROM {scorm} s
     JOIN {course_modules} cm ON cm.instance = s.id AND cm.module = :mod
     WHERE s.course = :course",
    ['mod' => $scorm_module_id, 'course' => COURSE_ID]
);
$mapa_scorm = []; // name => ['cmid' => int, 'scormid' => int]
foreach ($rows as $r) {
    $mapa_scorm[$r->name] = ['cmid' => (int)$r->cmid, 'scormid' => (int)$r->scormid];
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
        // Lê título do XML para achar o SCORM correspondente
        $xml = @simplexml_load_file($xml_path);
        if (!$xml) {
            echo "  [ERRO] Falha ao ler: " . basename($xml_path) . "\n";
            $total_erros++;
            continue;
        }
        $xml->registerXPathNamespace('lom', 'http://ltsc.ieee.org/xsd/LOM');
        $titulo_arr = $xml->xpath('//lom:general/lom:title/lom:string');
        $titulo = $titulo_arr ? trim((string)$titulo_arr[0]) : '';

        $dificuldade_lom = ler_dificuldade_lom($xml_path);

        if (empty($dificuldade_lom)) {
            echo "  [SEM DIFICULDADE] " . basename($xml_path) . " titulo='{$titulo}'\n";
            $total_pulados++;
            continue;
        }

        $nome_tag = $mapa_dificuldade[$dificuldade_lom] ?? null;
        if (!$nome_tag) {
            echo "  [VALOR DESCONHECIDO] dificuldade='{$dificuldade_lom}' em " . basename($xml_path) . "\n";
            $total_erros++;
            continue;
        }

        $scorm = $mapa_scorm[$titulo] ?? null;
        if (!$scorm) {
            echo "  [SEM SCORM] titulo='{$titulo}'\n";
            $total_erros++;
            continue;
        }

        $cmid = $scorm['cmid'];
        $context = context_module::instance($cmid);

        // set_item_tags substitui todas as tags da área — preservamos tags
        // existentes de outras coleções buscando só a de dificuldade
        $tags_atuais = core_tag_tag::get_item_tags_array('core', 'course_modules', $cmid);

        // Remove qualquer tag de dificuldade anterior e adiciona a nova
        $tags_outras = array_filter($tags_atuais, function($t) use ($mapa_dificuldade) {
            return !in_array($t, array_values($mapa_dificuldade));
        });
        $tags_novas = array_values($tags_outras);
        $tags_novas[] = $nome_tag;

        core_tag_tag::set_item_tags('core', 'course_modules', $cmid, $context, $tags_novas);

        echo "  [OK] cmid={$cmid} '{$titulo}' → {$nome_tag}\n";
        $total_ok++;
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "Associados: {$total_ok} | Sem dificuldade: {$total_pulados} | Erros: {$total_erros}\n";
