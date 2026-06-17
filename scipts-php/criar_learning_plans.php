<?php
/**
 * Cria Learning Plans e vínculos de competências no Moodle para cada aluno.
 *
 * Pré-requisito: competencias.php já deve ter sido executado, ou seja,
 * mdl_competency_usercomp já deve estar populado.
 *
 * O que este script faz:
 *   1. Busca todos os alunos que têm competências em mdl_competency_usercomp
 *   2. Para cada aluno, cria um Learning Plan (mdl_competency_plan) se não existir
 *   3. Adiciona as competências do aluno ao plano (mdl_competency_plancomp)
 *   4. Vincula usercomp ao plano (mdl_competency_usercompplan)
 *   5. Cria o vínculo aluno-competência-curso (mdl_competency_usercompcourse)
 *
 * sudo php /home/luiza/Desktop/TCC/criar_learning_plans.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');

global $DB, $CFG, $USER;
$USER = get_admin();

// ── Configurar aqui ───────────────────────────────────────────────────────────
define('COURSE_ID', 2);  // mesmo ID usado em competencias.php
define('PLAN_NAME', 'Plano de Aprendizagem TCC');
// ─────────────────────────────────────────────────────────────────────────────

$adminid = get_admin()->id;
$now     = time();

// ── Funções auxiliares ────────────────────────────────────────────────────────

function garantir_plan($userid, $adminid, $now) {
    global $DB;
    $existente = $DB->get_record('competency_plan', [
        'userid' => $userid,
        'name'   => PLAN_NAME,
    ]);
    if ($existente) {
        return $existente->id;
    }
    $plan = new stdClass();
    $plan->name         = PLAN_NAME;
    $plan->userid       = $userid;
    $plan->status       = 1; // 0=draft, 1=active, 2=complete
    $plan->duedate      = 0;
    $plan->description  = '';
    $plan->descriptionformat = FORMAT_HTML;
    $plan->timecreated  = $now;
    $plan->timemodified = $now;
    $plan->usermodified = $adminid;
    return $DB->insert_record('competency_plan', $plan);
}

function garantir_plancomp($planid, $competencyid, $ordem, $adminid, $now) {
    global $DB;
    $existente = $DB->get_record('competency_plancomp', [
        'planid'       => $planid,
        'competencyid' => $competencyid,
    ]);
    if ($existente) {
        return;
    }
    $pc = new stdClass();
    $pc->planid       = $planid;
    $pc->competencyid = $competencyid;
    $pc->sortorder    = $ordem;
    $pc->timecreated  = $now;
    $pc->timemodified = $now;
    $pc->usermodified = $adminid;
    $DB->insert_record('competency_plancomp', $pc);
}

function garantir_usercompplan($userid, $competencyid, $planid, $grade, $proficiency, $adminid, $now) {
    global $DB;
    $existente = $DB->get_record('competency_usercompplan', [
        'userid'       => $userid,
        'competencyid' => $competencyid,
        'planid'       => $planid,
    ]);
    if ($existente) {
        return;
    }
    $ucp = new stdClass();
    $ucp->userid       = $userid;
    $ucp->competencyid = $competencyid;
    $ucp->planid       = $planid;
    $ucp->proficiency  = $proficiency;
    $ucp->grade        = $grade;
    $ucp->timecreated  = $now;
    $ucp->timemodified = $now;
    $ucp->usermodified = $adminid;
    $DB->insert_record('competency_usercompplan', $ucp);
}

function garantir_usercompcourse($userid, $competencyid, $courseid, $grade, $proficiency, $adminid, $now) {
    global $DB;
    $existente = $DB->get_record('competency_usercompcourse', [
        'userid'       => $userid,
        'competencyid' => $competencyid,
        'courseid'     => $courseid,
    ]);
    if ($existente) {
        if ($existente->grade != $grade || $existente->proficiency != $proficiency) {
            $existente->grade        = $grade;
            $existente->proficiency  = $proficiency;
            $existente->timemodified = $now;
            $existente->usermodified = $adminid;
            $DB->update_record('competency_usercompcourse', $existente);
        }
        return;
    }
    $ucc = new stdClass();
    $ucc->userid       = $userid;
    $ucc->competencyid = $competencyid;
    $ucc->courseid     = $courseid;
    $ucc->proficiency  = $proficiency;
    $ucc->grade        = $grade;
    $ucc->timecreated  = $now;
    $ucc->timemodified = $now;
    $ucc->usermodified = $adminid;
    $DB->insert_record('competency_usercompcourse', $ucc);
}

// ── Verifica se o curso existe ────────────────────────────────────────────────
if (!$DB->record_exists('course', ['id' => COURSE_ID])) {
    die("ERRO: Curso com ID " . COURSE_ID . " não existe no Moodle.\n");
}

// ── Busca todos os alunos com competências já inseridas ───────────────────────
echo "Buscando alunos em mdl_competency_usercomp...\n";
$userids = $DB->get_fieldset_sql(
    "SELECT DISTINCT userid FROM {competency_usercomp} ORDER BY userid"
);
echo "   " . count($userids) . " aluno(s) encontrado(s).\n\n";

// ── Processa cada aluno ───────────────────────────────────────────────────────
$planos_criados      = 0;
$plan_comps_criados  = 0;
$usercompcourse_ok   = 0;

foreach ($userids as $userid) {
    // Todas as competências deste aluno
    $comps = $DB->get_records('competency_usercomp', ['userid' => $userid]);

    if (empty($comps)) {
        continue;
    }

    // 1. Cria ou recupera o Learning Plan
    $planid = garantir_plan($userid, $adminid, $now);
    $planos_criados++;

    // 2. Adiciona cada competência ao plano e ao curso
    $ordem = 0;
    foreach ($comps as $uc) {
        garantir_plancomp($planid, $uc->competencyid, ++$ordem, $adminid, $now);
        $plan_comps_criados++;

        garantir_usercompplan($userid, $uc->competencyid, $planid, $uc->grade, $uc->proficiency, $adminid, $now);

        garantir_usercompcourse(
            $userid,
            $uc->competencyid,
            COURSE_ID,
            $uc->grade,
            $uc->proficiency,
            $adminid,
            $now
        );
        $usercompcourse_ok++;
    }

    echo "   [OK] userid={$userid} — {$ordem} competência(s)\n";
}

// ── Resumo ────────────────────────────────────────────────────────────────────
echo "\n─────────────────────────────────────────────────────────────────\n";
echo "Concluído!\n";
echo "  Learning Plans criados/verificados: {$planos_criados}\n";
echo "  Competências adicionadas aos planos: {$plan_comps_criados}\n";
echo "  Vínculos aluno-competência-curso:    {$usercompcourse_ok}\n";
