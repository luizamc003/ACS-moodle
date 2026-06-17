<?php
/**
 * Associa cada SCORM do curso 2 às competências (conceitos) correspondentes,
 * lendo os CSVs que mapeiam material_id → siglas de competência.
 *
 * Uso:
 *   sudo php /home/luiza/Desktop/TCC/associar_scorm_competencias.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');

global $DB, $USER;
$USER = get_admin();

define('COURSE_ID', 2);
define('FRAMEWORK_ID', 3); // "Conceitos TCC"
define('LOM_BASE_DIR', '/home/luiza/Desktop/TCC');

// Mapeamento CSV → pasta LOM (para casar o material_id com o SCORM correto)
$arquivos = [
    'Algorithm.csv'                          => 'Algorithm',
    'Computer_History.csv'                   => 'History of Computation And Computer History',
    'Database_And_Software_Engineering.csv'  => 'Database And Software Engineering',
    'Network_Computer.csv'                   => 'Computer Network',
    'Numeric_System_and_Logic.csv'           => 'Numeric System And Logic',
    'Operation_System_And_Computer_Organization.csv' => 'Operation System And Computer Organization',
];

// ── Carrega mapa idnumber → competency id do framework 3 ─────────────────────
$competencias = $DB->get_records('competency', ['competencyframeworkid' => FRAMEWORK_ID], '', 'id, idnumber');
$mapa_comp = []; // idnumber => id
foreach ($competencias as $c) {
    $mapa_comp[$c->idnumber] = $c->id;
}
echo "Competências carregadas: " . count($mapa_comp) . "\n\n";

// ── Carrega mapa nome_scorm → cmid para o curso 2 ────────────────────────────
$scorm_module_id = $DB->get_field('modules', 'id', ['name' => 'scorm']);
$rows = $DB->get_records_sql(
    "SELECT s.name, cm.id AS cmid
     FROM {scorm} s
     JOIN {course_modules} cm ON cm.instance = s.id AND cm.module = :mod
     WHERE s.course = :course",
    ['mod' => $scorm_module_id, 'course' => COURSE_ID]
);
$mapa_scorm = []; // name => cmid
foreach ($rows as $r) {
    $mapa_scorm[$r->name] = $r->cmid;
}
echo "SCORMs carregados: " . count($mapa_scorm) . "\n\n";

// ── Função para ler CSV com separador ; e encoding latin1 ────────────────────
function ler_csv(string $path): array {
    $linhas = [];
    $handle = fopen($path, 'r');
    while (($linha = fgets($handle)) !== false) {
        $linha = mb_convert_encoding(rtrim($linha, "\r\n"), 'UTF-8', 'ISO-8859-1');
        $cols  = explode(';', $linha);
        if (count($cols) < 3) continue; // pula linhas sem competência
        $linhas[] = $cols;
    }
    fclose($handle);
    return $linhas;
}

// ── Associa SCORM → competência em mdl_competency_coursemodulecomp ────────────
function associar(int $cmid, int $competencyid): bool {
    global $DB, $USER;

    $existe = $DB->record_exists('competency_modulecomp', [
        'cmid'         => $cmid,
        'competencyid' => $competencyid,
    ]);
    if ($existe) return false;

    $rec = new stdClass();
    $rec->cmid          = $cmid;
    $rec->competencyid  = $competencyid;
    $rec->ruleoutcome   = 0;
    $rec->overridegrade = 0;
    $rec->sortorder     = 0;
    $rec->timecreated   = time();
    $rec->timemodified  = time();
    $rec->usermodified  = $USER->id;
    $DB->insert_record('competency_modulecomp', $rec);
    return true;
}

// ── Execução principal ────────────────────────────────────────────────────────
$total_ok      = 0;
$total_pulados = 0;
$total_erros   = 0;

foreach ($arquivos as $csv_file => $disciplina) {
    $csv_path = LOM_BASE_DIR . '/' . $csv_file;
    if (!file_exists($csv_path)) {
        echo "[AVISO] CSV não encontrado: {$csv_file}\n";
        continue;
    }

    echo "══ {$disciplina} ══\n";
    $linhas = ler_csv($csv_path);

    foreach ($linhas as $cols) {
        $material_id = trim($cols[0]);
        $titulo      = trim($cols[1]);
        $siglas      = array_map('trim', array_slice($cols, 2));

        // Encontra o SCORM pelo título (que veio do LOM)
        // Tenta pelo título primeiro, depois por "Material {id}" como fallback
        $cmid = $mapa_scorm[$titulo] ?? $mapa_scorm["Material {$material_id}"] ?? null;

        if (!$cmid) {
            echo "  [SEM SCORM] id={$material_id} titulo='{$titulo}'\n";
            $total_erros++;
            continue;
        }

        foreach ($siglas as $sigla) {
            if (empty($sigla)) continue;

            $competencyid = $mapa_comp[$sigla] ?? null;
            if (!$competencyid) {
                echo "  [SEM COMP] sigla={$sigla} (material id={$material_id})\n";
                $total_erros++;
                continue;
            }

            if (associar($cmid, $competencyid)) {
                echo "  [OK] cmid={$cmid} '{$titulo}' → {$sigla}\n";
                $total_ok++;
            } else {
                $total_pulados++;
            }
        }
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "Associações criadas: {$total_ok} | Já existiam: {$total_pulados} | Erros: {$total_erros}\n";
