<?php
/**
 * Cria competências no Moodle a partir dos conceitos do TCC.
 *
 * Passos:
 *   1. Cria uma escala de 5 pontos (Insuficiente → Expert)
 *   2. Cria um framework "Conceitos TCC"
 *   3. Cria uma competência por conceito do banco TCC
 *   4. Associa cada competência ao curso configurado
 *   5. Insere a habilidade de cada aluno por conceito em competency_usercomp
 *
 * sudo php /home/luiza/Desktop/TCC/competencias.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');

global $DB, $CFG, $USER;
$USER = get_admin();

// ── Configurar aqui ───────────────────────────────────────────────────────────
define('COURSE_ID', 2);  // ID do curso Moodle a receber as competências
// ─────────────────────────────────────────────────────────────────────────────

$tcc = new mysqli('localhost', 'root', 'senha', 'introducao_a_computacao');
if ($tcc->connect_error) {
    die("Erro ao conectar no banco TCC: " . $tcc->connect_error . "\n");
}
$tcc->set_charset('utf8');

// Verifica se o curso existe
if (!$DB->record_exists('course', ['id' => COURSE_ID])) {
    die("ERRO: Curso com ID " . COURSE_ID . " não existe no Moodle. Ajuste a constante COURSE_ID.\n");
}

// ── Funções auxiliares ────────────────────────────────────────────────────────

function garantir_escala()
{
    global $DB;
    $nome = 'Escala TCC (1-5)';
    $existente = $DB->get_record('scale', ['name' => $nome]);
    if ($existente) {
        return $existente->id;
    }
    $novo = new stdClass();
    $novo->courseid = 0;
    $novo->userid = get_admin()->id;
    $novo->name = $nome;
    $novo->scale = 'Insuficiente,Básico,Intermediário,Avançado,Expert';
    $novo->description = 'Escala de habilidade do TCC: 1=Insuficiente, 3=Intermediário (proficiente), 5=Expert.';
    $novo->descriptionformat = 0;
    $novo->timemodified = time();
    return $DB->insert_record('scale', $novo);
}

function garantir_framework($scaleid)
{
    global $DB;
    $idnumber = 'tcc_conceitos';
    $existente = $DB->get_record('competency_framework', ['idnumber' => $idnumber]);
    if ($existente) {
        return $existente->id;
    }
    $now = time();
    $novo = new stdClass();
    $novo->shortname = 'Conceitos TCC';
    $novo->idnumber = $idnumber;
    $novo->description = 'Competências baseadas nos conceitos do TCC de Sequenciamento Curricular Adaptivo.';
    $novo->descriptionformat = FORMAT_HTML;
    $novo->visible = 1;
    $novo->scaleid = $scaleid;
    // proficient=3 significa que grau >= 3 (Intermediário) conta como proficiente
    $novo->scaleconfiguration = json_encode([
        ['scaleid' => (string) $scaleid, 'scaledefault' => 1, 'proficient' => 3]
    ]);
    $novo->contextid = context_system::instance()->id;
    $novo->taxonomies = '';
    $novo->timecreated = $now;
    $novo->timemodified = $now;
    $novo->usermodified = get_admin()->id;
    return $DB->insert_record('competency_framework', $novo);
}

function garantir_competencia($sigla, $nome, $frameworkid, $ordem)
{
    global $DB;
    $existente = $DB->get_record('competency', [
        'idnumber' => $sigla,
        'competencyframeworkid' => $frameworkid,
    ]);
    if ($existente) {
        return $existente->id;
    }
    $now = time();
    $novo = new stdClass();
    $novo->shortname = $sigla;
    $novo->idnumber = $sigla;
    $novo->description = $nome;
    $novo->descriptionformat = FORMAT_HTML;
    $novo->sortorder = $ordem;
    $novo->parentid = 0;
    $novo->path = '/0/'; // atualizado abaixo
    $novo->ruleoutcome = 0;
    $novo->ruletype = null;
    $novo->ruleconfig = null;
    $novo->scaleid = null; // herda do framework
    $novo->scaleconfiguration = null;
    $novo->competencyframeworkid = $frameworkid;
    $novo->timecreated = $now;
    $novo->timemodified = $now;
    $novo->usermodified = get_admin()->id;
    $id = $DB->insert_record('competency', $novo);
    // path hierárquico: /0/{id}/ para competências raiz
    $DB->set_field('competency', 'path', '/0/' . $id . '/', ['id' => $id]);
    return $id;
}

function garantir_comp_curso($courseid, $competencyid, $ordem)
{
    global $DB;
    $existente = $DB->get_record('competency_coursecomp', [
        'courseid' => $courseid,
        'competencyid' => $competencyid,
    ]);
    if ($existente) {
        return;
    }
    $now = time();
    $novo = new stdClass();
    $novo->courseid = $courseid;
    $novo->competencyid = $competencyid;
    $novo->ruleoutcome = 0;
    $novo->sortorder = $ordem;
    $novo->timecreated = $now;
    $novo->timemodified = $now;
    $novo->usermodified = get_admin()->id;
    $DB->insert_record('competency_coursecomp', $novo);
}

function salvar_user_competency($userid, $competencyid, $habilidade)
{
    global $DB;
    // Mapeia habilidade (double 1.0–5.0) para grau inteiro e proficiência
    $grade = max(1, min(5, (int) round((float) $habilidade)));
    $proficiency = ($grade >= 3) ? 1 : 0;
    $now = time();
    $adminid = get_admin()->id;

    $existente = $DB->get_record('competency_usercomp', [
        'userid' => $userid,
        'competencyid' => $competencyid,
    ]);
    if ($existente) {
        $existente->grade = $grade;
        $existente->proficiency = $proficiency;
        $existente->timemodified = $now;
        $existente->usermodified = $adminid;
        $DB->update_record('competency_usercomp', $existente);
    } else {
        $novo = new stdClass();
        $novo->userid = $userid;
        $novo->competencyid = $competencyid;
        $novo->status = 0;
        $novo->reviewerid = null;
        $novo->proficiency = $proficiency;
        $novo->grade = $grade;
        $novo->timecreated = $now;
        $novo->timemodified = $now;
        $novo->usermodified = $adminid;
        $DB->insert_record('competency_usercomp', $novo);
    }
}

// ── 1. Escala ─────────────────────────────────────────────────────────────────
echo "1. Criando escala...\n";
$scaleid = garantir_escala();
echo "   Escala ID: {$scaleid}\n\n";

// ── 2. Framework ──────────────────────────────────────────────────────────────
echo "2. Criando framework...\n";
$frameworkid = garantir_framework($scaleid);
echo "   Framework ID: {$frameworkid}\n\n";

// ── 3 e 4. Competências + associação ao curso ─────────────────────────────────
echo "3. Criando competências e associando ao curso " . COURSE_ID . "...\n";
$conceitos = $tcc->query("SELECT * FROM conceito ORDER BY id");
$competency_ids = []; // [id_conceito => competency_id_moodle]
$ordem = 0;

while ($c = $conceitos->fetch_object()) {
    $compid = garantir_competencia($c->sigla, $c->nome, $frameworkid, ++$ordem);
    $competency_ids[$c->id] = $compid;
    garantir_comp_curso(COURSE_ID, $compid, $ordem);
    echo "   [OK] {$c->sigla} — {$c->nome}\n";
}
echo "\n";

// ── 5. Habilidade por aluno por conceito ──────────────────────────────────────
echo "4. Inserindo habilidades por aluno em competency_usercomp...\n";

$sql = "
    SELECT ac.id_aluno, ac.id_conceito, ac.habilidade,
           a.matricula
    FROM aluno_conceito ac
    JOIN aluno a ON a.id = ac.id_aluno
    ORDER BY ac.id_aluno, ac.id_conceito
";
$registros = $tcc->query($sql);
if (!$registros) {
    die("Erro na consulta: " . $tcc->error . "\n");
}

$inseridos = 0;
$sem_usuario = 0;
$sem_conceito = 0;

while ($r = $registros->fetch_object()) {
    $username = strtolower(trim($r->matricula));
    $moodle_user = $DB->get_record('user', ['username' => $username]);
    if (!$moodle_user) {
        $sem_usuario++;
        continue;
    }
    if (!isset($competency_ids[$r->id_conceito])) {
        $sem_conceito++;
        continue;
    }
    salvar_user_competency($moodle_user->id, $competency_ids[$r->id_conceito], $r->habilidade);
    $inseridos++;
}

$tcc->close();

echo "\n─────────────────────────────────────────────────────────────────\n";
echo "Concluído!\n";
echo "  Competências criadas/verificadas: " . count($competency_ids) . "\n";
echo "  Habilidades inseridas:            {$inseridos}\n";
echo "  Alunos sem usuário Moodle:        {$sem_usuario}\n";
echo "  Conceitos sem mapeamento:         {$sem_conceito}\n";
