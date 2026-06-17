<?php
/**
 * Cria SCORMs com metadados LOM no Moodle.
 *
 * Cada pasta de disciplina vira uma seção (semana) dentro do curso ID 2.
 * Cada XML LOM vira um SCORM com metadados embutidos no imsmanifest.xml.
 *
 * Uso:
 *   sudo php /home/luiza/Desktop/TCC/criar_scorms_lom.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');
require_once($CFG->dirroot . '/mod/scorm/lib.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

global $DB, $CFG, $USER;
$USER = get_admin();

// ── Curso único onde todas as disciplinas serão seções ───────────────────────
define('COURSE_ID', 2);

// ── Disciplinas = nomes das seções (ordem define a numeração das semanas) ────
$disciplinas = [
    'Algorithm',
    'Computer Network',
    'Database And Software Engineering',
    'History of Computation And Computer History',
    'Numeric System And Logic',
    'Operation System And Computer Organization',
];

// Pasta raiz onde ficam as subpastas de disciplina
define('LOM_BASE_DIR', '/home/luiza/Desktop/TCC');

// ─────────────────────────────────────────────────────────────────────────────

$scorm_module = $DB->get_record('modules', ['name' => 'scorm'], '*', MUST_EXIST);

// ── Converte duração ISO 8601 (PT28M18S) em segundos ─────────────────────────
function iso8601_para_segundos(string $dur): int {
    if (!preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $dur, $m)) return 0;
    return (int)($m[1] ?? 0) * 3600 + (int)($m[2] ?? 0) * 60 + (int)($m[3] ?? 0);
}

// ── Lê um XML LOM e retorna array com os metadados ───────────────────────────
function parse_lom(string $caminho): ?array {
    $xml = @simplexml_load_file($caminho);
    if (!$xml) return null;

    $ns = 'http://ltsc.ieee.org/xsd/LOM';
    $xml->registerXPathNamespace('lom', $ns);

    $get = function(string $xpath) use ($xml): string {
        $r = $xml->xpath($xpath);
        return $r ? trim((string)$r[0]) : '';
    };

    $getAll = function(string $xpath) use ($xml): array {
        $r = $xml->xpath($xpath);
        return $r ? array_map(fn($v) => trim((string)$v), $r) : [];
    };

    $id    = $get('//lom:general/lom:identifier/lom:entry');
    $titulo = $get('//lom:general/lom:title/lom:string');
    $descricao = $get('//lom:general/lom:description/lom:string');
    $keywords  = $getAll('//lom:general/lom:keyword/lom:string');
    $formato   = $get('//lom:technical/lom:format');
    $tamanho   = $get('//lom:technical/lom:size');

    $interatividade = $get('//lom:educational/lom:interactivityType/lom:value');
    $nivel_inter    = $get('//lom:educational/lom:interactivityLevel/lom:value');
    $tipos_recurso  = $getAll('//lom:educational/lom:learningResourceType/lom:value');

    // Pega a última ocorrência de difficulty (alguns XMLs têm duas)
    $dificuldades = $getAll('//lom:educational/lom:difficulty/lom:value');
    $dificuldade  = $dificuldades ? end($dificuldades) : '';

    $duracao_iso = $get('//lom:educational/lom:typicalLearningTime/lom:duration');
    $duracao_seg = iso8601_para_segundos($duracao_iso);

    return compact(
        'id', 'titulo', 'descricao', 'keywords', 'formato', 'tamanho',
        'interatividade', 'nivel_inter', 'tipos_recurso', 'dificuldade',
        'duracao_iso', 'duracao_seg'
    );
}

// ── Gera imsmanifest.xml SCORM 1.2 com metadados LOM embutidos ───────────────
function build_manifest(array $lom, string $disciplina): string {
    $id     = htmlspecialchars($lom['id'],     ENT_XML1, 'UTF-8');
    $titulo = htmlspecialchars($lom['titulo'], ENT_XML1, 'UTF-8');
    $desc   = htmlspecialchars($lom['descricao'], ENT_XML1, 'UTF-8');
    $disc   = htmlspecialchars($disciplina,    ENT_XML1, 'UTF-8');

    $keywords_xml = '';
    foreach ($lom['keywords'] as $kw) {
        $kw_esc = htmlspecialchars($kw, ENT_XML1, 'UTF-8');
        $keywords_xml .= "          <imsmd:keyword><imsmd:langstring xml:lang=\"pt-BR\">{$kw_esc}</imsmd:langstring></imsmd:keyword>\n";
    }

    $tipos_xml = '';
    foreach ($lom['tipos_recurso'] as $tipo) {
        $tipo_esc = htmlspecialchars($tipo, ENT_XML1, 'UTF-8');
        $tipos_xml .= "          <imsmd:learningresourcetype>\n";
        $tipos_xml .= "            <imsmd:source><imsmd:langstring xml:lang=\"x-none\">LOMv1.0</imsmd:langstring></imsmd:source>\n";
        $tipos_xml .= "            <imsmd:value><imsmd:langstring xml:lang=\"x-none\">{$tipo_esc}</imsmd:langstring></imsmd:value>\n";
        $tipos_xml .= "          </imsmd:learningresourcetype>\n";
    }

    $fmt  = htmlspecialchars($lom['formato'],       ENT_XML1, 'UTF-8');
    $tam  = htmlspecialchars($lom['tamanho'],        ENT_XML1, 'UTF-8');
    $intr = htmlspecialchars($lom['interatividade'], ENT_XML1, 'UTF-8');
    $niv  = htmlspecialchars($lom['nivel_inter'],    ENT_XML1, 'UTF-8');
    $dif  = htmlspecialchars($lom['dificuldade'],    ENT_XML1, 'UTF-8');
    $dur  = htmlspecialchars($lom['duracao_iso'],    ENT_XML1, 'UTF-8');

    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<manifest identifier="material_{$id}" version="1"
  xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
  xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"
  xmlns:imsmd="http://www.imsglobal.org/xsd/imsmd_rootv1p2p1"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2
    imscp_rootv1p1p2.xsd
    http://www.adlnet.org/xsd/adlcp_rootv1p2
    adlcp_rootv1p2.xsd">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>1.2</schemaversion>
    <imsmd:lom>
      <imsmd:general>
        <imsmd:identifier>{$id}</imsmd:identifier>
        <imsmd:title>
          <imsmd:langstring xml:lang="pt-BR">{$titulo}</imsmd:langstring>
        </imsmd:title>
        <imsmd:language>pt-BR</imsmd:language>
        <imsmd:description>
          <imsmd:langstring xml:lang="pt-BR">{$desc}</imsmd:langstring>
        </imsmd:description>
        <imsmd:subject>
          <imsmd:langstring xml:lang="pt-BR">{$disc}</imsmd:langstring>
        </imsmd:subject>
{$keywords_xml}      </imsmd:general>
      <imsmd:technical>
        <imsmd:format>{$fmt}</imsmd:format>
        <imsmd:size>{$tam}</imsmd:size>
      </imsmd:technical>
      <imsmd:educational>
        <imsmd:interactivitytype>
          <imsmd:source><imsmd:langstring xml:lang="x-none">LOMv1.0</imsmd:langstring></imsmd:source>
          <imsmd:value><imsmd:langstring xml:lang="x-none">{$intr}</imsmd:langstring></imsmd:value>
        </imsmd:interactivitytype>
{$tipos_xml}        <imsmd:interactivitylevel>
          <imsmd:source><imsmd:langstring xml:lang="x-none">LOMv1.0</imsmd:langstring></imsmd:source>
          <imsmd:value><imsmd:langstring xml:lang="x-none">{$niv}</imsmd:langstring></imsmd:value>
        </imsmd:interactivitylevel>
        <imsmd:difficulty>
          <imsmd:source><imsmd:langstring xml:lang="x-none">LOMv1.0</imsmd:langstring></imsmd:source>
          <imsmd:value><imsmd:langstring xml:lang="x-none">{$dif}</imsmd:langstring></imsmd:value>
        </imsmd:difficulty>
        <imsmd:typicallearningtime>
          <imsmd:datetime>{$dur}</imsmd:datetime>
        </imsmd:typicallearningtime>
        <imsmd:language>pt-BR</imsmd:language>
      </imsmd:educational>
    </imsmd:lom>
  </metadata>
  <organizations default="ORG-{$id}">
    <organization identifier="ORG-{$id}">
      <title>{$titulo}</title>
      <item identifier="ITEM-{$id}" identifierref="RES-{$id}">
        <title>{$titulo}</title>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="RES-{$id}" type="webcontent"
              adlcp:scormtype="sco" href="index.html">
      <file href="index.html"/>
    </resource>
  </resources>
</manifest>
XML;
}

// ── Gera index.html com resumo do material ────────────────────────────────────
function build_index(array $lom, string $disciplina): string {
    $titulo   = htmlspecialchars($lom['titulo'],    ENT_QUOTES, 'UTF-8');
    $desc     = htmlspecialchars($lom['descricao'], ENT_QUOTES, 'UTF-8');
    $disc     = htmlspecialchars($disciplina,       ENT_QUOTES, 'UTF-8');
    $dif      = htmlspecialchars($lom['dificuldade'], ENT_QUOTES, 'UTF-8');
    $dur_min  = $lom['duracao_seg'] > 0 ? round($lom['duracao_seg'] / 60) . ' min' : '-';
    $keywords = htmlspecialchars(implode(', ', $lom['keywords']), ENT_QUOTES, 'UTF-8');
    $tipos    = htmlspecialchars(implode(', ', $lom['tipos_recurso']), ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>{$titulo}</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 2em auto; padding: 0 1em; }
    h1 { color: #333; }
    table { border-collapse: collapse; width: 100%; margin-top: 1em; }
    td, th { border: 1px solid #ccc; padding: .5em .8em; text-align: left; }
    th { background: #f0f0f0; width: 35%; }
  </style>
</head>
<body>
  <h1>{$titulo}</h1>
  <p>{$desc}</p>
  <table>
    <tr><th>Disciplina</th><td>{$disc}</td></tr>
    <tr><th>Dificuldade</th><td>{$dif}</td></tr>
    <tr><th>Tempo estimado</th><td>{$dur_min}</td></tr>
    <tr><th>Tipos de recurso</th><td>{$tipos}</td></tr>
    <tr><th>Palavras-chave</th><td>{$keywords}</td></tr>
  </table>
  <script>
  (function () {
    var API = null, win = window.parent;
    for (var i = 0; i < 7 && !API; i++, win = win.parent) {
      if (win.API) { API = win.API; break; }
    }
    if (API) {
      API.LMSInitialize('');
      API.LMSSetValue('cmi.core.lesson_status', 'completed');
      API.LMSCommit('');
      API.LMSFinish('');
    }
  })();
  </script>
</body>
</html>
HTML;
}

// ── Cria contexto de módulo no banco ──────────────────────────────────────────
function criar_contexto_modulo(int $cmid): context {
    global $DB;
    $reg = $DB->get_record('context', [
        'contextlevel' => CONTEXT_MODULE,
        'instanceid'   => $cmid,
    ]);
    if ($reg) return context::instance_by_id($reg->id);

    $cm         = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
    $course_ctx = context_course::instance($cm->course);

    $ctx              = new stdClass();
    $ctx->contextlevel = CONTEXT_MODULE;
    $ctx->instanceid   = $cmid;
    $ctx->parentid     = $course_ctx->id;
    $ctx->depth        = $course_ctx->depth + 1;
    $ctx->path         = '';
    $ctx->locked       = 0;
    $ctxid = $DB->insert_record('context', $ctx);
    $DB->set_field('context', 'path', $course_ctx->path . '/' . $ctxid, ['id' => $ctxid]);
    return context::instance_by_id($ctxid);
}

// ── Garante seção no curso ────────────────────────────────────────────────────
function garantir_secao(int $courseid, string $nome): stdClass {
    global $DB;
    $ex = $DB->get_record_select('course_sections',
        'course = ? AND name = ?', [$courseid, $nome]);
    if ($ex) return $ex;

    $max = $DB->get_field_sql(
        'SELECT MAX(section) FROM {course_sections} WHERE course = ?', [$courseid]);

    $nova = new stdClass();
    $nova->course        = $courseid;
    $nova->section       = ($max ?? 0) + 1;
    $nova->name          = $nome;
    $nova->summary       = '';
    $nova->summaryformat = FORMAT_HTML;
    $nova->sequence      = '';
    $nova->visible       = 1;
    $nova->timemodified  = time();
    $id = $DB->insert_record('course_sections', $nova);
    return $DB->get_record('course_sections', ['id' => $id]);
}

// ── Insere um SCORM com metadados LOM no Moodle ───────────────────────────────
// Retorna ['cmid' => int, 'pulado' => bool]
function inserir_scorm(int $courseid, int $sectionid, int $modid,
                       array $lom, string $disciplina): array {
    global $DB;

    $nome = $lom['titulo'] ?: "Material {$lom['id']}";

    // Evita duplicata pelo nome dentro do curso
    $existe = $DB->get_record_sql(
        "SELECT cm.id FROM {course_modules} cm
         JOIN {scorm} s ON s.id = cm.instance AND cm.module = :mod
         WHERE cm.course = :course AND s.name = :nome",
        ['mod' => $modid, 'course' => $courseid, 'nome' => $nome]
    );
    if ($existe) {
        echo "  [PULADO] {$nome} — já existe (cmid={$existe->id})\n";
        return ['cmid' => $existe->id, 'pulado' => true];
    }

    // Cria o zip em memória
    $zippath = tempnam(sys_get_temp_dir(), 'lom_scorm') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zippath, ZipArchive::CREATE) !== true) {
        throw new Exception("Não foi possível criar o zip temporário.");
    }
    $zip->addFromString('imsmanifest.xml', build_manifest($lom, $disciplina));
    $zip->addFromString('index.html',      build_index($lom, $disciplina));
    $zip->close();

    $zipname = "material_{$lom['id']}.zip";
    $now = time();

    // Registro mdl_scorm
    $scorm = new stdClass();
    $scorm->course                   = $courseid;
    $scorm->name                     = $nome;
    $scorm->intro                    = "<p>{$lom['descricao']}</p>";
    $scorm->introformat              = FORMAT_HTML;
    $scorm->scormtype                = 'local';
    $scorm->reference                = $zipname;
    $scorm->version                  = 'SCORM_1.2';
    $scorm->maxgrade                 = 100;
    $scorm->grademethod              = 0;
    $scorm->whatgrade                = 0;
    $scorm->maxattempt               = 0;
    $scorm->forcecompleted           = 0;
    $scorm->forcenewattempt          = 0;
    $scorm->lastattemptlock          = 0;
    $scorm->displayattemptstatus     = 1;
    $scorm->displaycoursestructure   = 0;
    $scorm->updatefreq               = 0;
    $scorm->sha1hash                 = sha1_file($zippath);
    $scorm->md5hash                  = md5_file($zippath);
    $scorm->revision                 = 1;
    $scorm->launch                   = 0;
    $scorm->popup                    = 0;
    $scorm->width                    = 100;
    $scorm->height                   = 500;
    $scorm->options                  = '';
    $scorm->tracked                  = 1;
    $scorm->nav                      = 1;
    $scorm->navpositionleft          = -100;
    $scorm->navpositiontop           = -100;
    $scorm->hidetoc                  = 0;
    $scorm->hidenav                  = 0;
    $scorm->auto                     = 0;
    $scorm->autocommit               = 0;
    $scorm->timeopen                 = 0;
    $scorm->timeclose                = 0;
    $scorm->timemodified             = $now;
    $scorm->skipview                 = 1;
    $scorm->displayoptions           = '';
    $scorm->completionstatusrequired = null;
    $scorm->completionscorerequired  = null;
    $scorm->completionstatusallscos  = null;
    $scormid = $DB->insert_record('scorm', $scorm);

    // course_modules
    $cm = new stdClass();
    $cm->course          = $courseid;
    $cm->module          = $modid;
    $cm->instance        = $scormid;
    $cm->section         = $sectionid;
    $cm->visible         = 1;
    $cm->visibleold      = 1;
    $cm->groupmode       = 0;
    $cm->groupingid      = 0;
    $cm->completion      = 0;
    $cm->completionview  = 0;
    $cm->completionexpected = 0;
    $cm->showdescription = 0;
    $cm->added           = $now;
    $cm->score           = 0;
    $cm->indent          = 0;
    $cmid = $DB->insert_record('course_modules', $cm);

    // Armazena o zip no filestore do Moodle
    $context = criar_contexto_modulo($cmid);
    $fs = get_file_storage();
    $fr = [
        'contextid'    => $context->id,
        'component'    => 'mod_scorm',
        'filearea'     => 'package',
        'itemid'       => 0,
        'filepath'     => '/',
        'filename'     => $zipname,
        'timecreated'  => $now,
        'timemodified' => $now,
    ];
    if (!$fs->file_exists($context->id, 'mod_scorm', 'package', 0, '/', $zipname)) {
        $fs->create_file_from_pathname($fr, $zippath);
    }
    unlink($zippath);

    // SCO pai (organização) — obrigatório para o player não quebrar
    $sco_org = new stdClass();
    $sco_org->scorm        = $scormid;
    $sco_org->manifest     = "material_{$lom['id']}";
    $sco_org->organization = '';
    $sco_org->parent       = '';
    $sco_org->identifier   = "ORG-{$lom['id']}";
    $sco_org->launch       = '';
    $sco_org->scormtype    = '';
    $sco_org->title        = $nome;
    $sco_org->sortorder    = 0;
    $DB->insert_record('scorm_scoes', $sco_org);

    // SCO filho (conteúdo real)
    $sco = new stdClass();
    $sco->scorm        = $scormid;
    $sco->manifest     = "material_{$lom['id']}";
    $sco->organization = "ORG-{$lom['id']}";
    $sco->parent       = "ORG-{$lom['id']}";
    $sco->identifier   = "ITEM-{$lom['id']}";
    $sco->launch       = 'index.html';
    $sco->scormtype    = 'sco';
    $sco->title        = $nome;
    $sco->sortorder    = 1;
    $scoid = $DB->insert_record('scorm_scoes', $sco);

    $DB->set_field('scorm', 'launch', $scoid, ['id' => $scormid]);

    // Adiciona à sequência da seção
    $secao = $DB->get_record('course_sections', ['id' => $sectionid]);
    $seq   = $secao->sequence ? $secao->sequence . ',' . $cmid : (string)$cmid;
    $DB->set_field('course_sections', 'sequence', $seq, ['id' => $sectionid]);

    return ['cmid' => $cmid, 'pulado' => false];
}

// ── Execução principal ────────────────────────────────────────────────────────
$total_criados = 0;
$total_pulados = 0;
$total_erros   = 0;

if (!$DB->record_exists('course', ['id' => COURSE_ID])) {
    die("ERRO: Curso ID=" . COURSE_ID . " não existe no Moodle.\n");
}

foreach ($disciplinas as $disciplina) {
    $pasta = LOM_BASE_DIR . '/' . $disciplina;

    if (!is_dir($pasta)) {
        echo "\n[AVISO] Pasta não encontrada: {$pasta}\n";
        continue;
    }

    echo "\n══ Seção: {$disciplina} ══\n";

    // Cada disciplina = uma seção (semana) no curso
    $secao = garantir_secao(COURSE_ID, $disciplina);

    $xmls = glob($pasta . '/*.xml');
    sort($xmls);

    foreach ($xmls as $xml_path) {
        $lom = parse_lom($xml_path);
        if (!$lom) {
            echo "  [ERRO] Falha ao ler: " . basename($xml_path) . "\n";
            $total_erros++;
            continue;
        }
        if (empty($lom['titulo'])) {
            $lom['titulo'] = 'Material ' . $lom['id'];
        }

        try {
            $result = inserir_scorm(COURSE_ID, $secao->id, $scorm_module->id, $lom, $disciplina);
            if ($result['pulado']) {
                $total_pulados++;
            } else {
                echo "  [OK] {$lom['titulo']} (id={$lom['id']}) → cmid={$result['cmid']}\n";
                $total_criados++;
            }
        } catch (Exception $e) {
            echo "  [ERRO] {$lom['titulo']}: " . $e->getMessage() . "\n";
            $total_erros++;
        }
    }
}

rebuild_course_cache(COURSE_ID, true);

echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "Concluído — Criados: {$total_criados} | Pulados: {$total_pulados} | Erros: {$total_erros}\n";
