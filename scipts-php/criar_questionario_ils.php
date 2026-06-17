<?php
/**
 * Cria o questionário ILS (Index of Learning Styles) de Felder-Silverman
 * no Moodle com 44 questões de múltipla escolha (a/b).
 *
 * Cria 4 questionários separados, um por dimensão:
 *   - Ativo/Reflexivo   (atiref)
 *   - Sensorial/Intuitivo (semint)
 *   - Visual/Verbal     (visver)
 *   - Sequencial/Global (seqglo)
 *
 * Cada questionário tem nota de 0 a 11 (1 ponto por resposta "b").
 * A conversão para -11 a +11 é feita via Item Calculado no Gradebook:
 *   score = (2 * nota_bruta) - 11
 *
 * Uso:
 *   sudo php /home/luiza/Desktop/TCC/criar_questionario_ils.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/public/config.php');

global $DB, $CFG, $USER;
$USER = get_admin();

require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->libdir  . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');

define('COURSE_ID', 2);

// ── Questões do ILS com mapeamento de dimensão ────────────────────────────────
// polo_b = nome do polo correspondente à resposta "b" (1 ponto)
// polo_a = nome do polo correspondente à resposta "a" (0 ponto)
// dimensao: atiref | semint | visver | seqglo

$questoes = [
    // ── Ativo(a=0) / Reflexivo(b=1) ──────────────────────────────────────────
    ['n' => 1,  'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'I understand something better after I',
     'opcao_a'   => 'try it out.',
     'opcao_b'   => 'think it through.'],

    ['n' => 5,  'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'When I am learning something new, it helps me to',
     'opcao_a'   => 'talk about it.',
     'opcao_b'   => 'think about it.'],

    ['n' => 9,  'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'In a study group working on difficult material, I am more likely to',
     'opcao_a'   => 'jump in and contribute ideas.',
     'opcao_b'   => 'sit back and listen.'],

    ['n' => 13, 'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'In classes I have taken',
     'opcao_a'   => 'I have usually gotten to know many of the students.',
     'opcao_b'   => 'I have rarely gotten to know many of the students.'],

    ['n' => 17, 'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'When I start a homework problem, I am more likely to',
     'opcao_a'   => 'start working on the solution immediately.',
     'opcao_b'   => 'try to fully understand the problem first.'],

    ['n' => 21, 'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'I prefer to study',
     'opcao_a'   => 'in a study group.',
     'opcao_b'   => 'alone.'],

    ['n' => 25, 'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'I would rather first',
     'opcao_a'   => 'try things out.',
     'opcao_b'   => 'think about how I\'m going to do it.'],

    ['n' => 29, 'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'I more easily remember',
     'opcao_a'   => 'something I have done.',
     'opcao_b'   => 'something I have thought a lot about.'],

    ['n' => 33, 'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'When I have to work on a group project, I first want to',
     'opcao_a'   => 'have "group brainstorming" where everyone contributes ideas.',
     'opcao_b'   => 'brainstorm individually and then come together as a group to compare ideas.'],

    ['n' => 37, 'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'I am more likely to be considered',
     'opcao_a'   => 'outgoing.',
     'opcao_b'   => 'reserved.'],

    ['n' => 41, 'dim' => 'atiref', 'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo',
     'enunciado' => 'The idea of doing homework in groups, with one grade for the entire group',
     'opcao_a'   => 'appeals to me.',
     'opcao_b'   => 'does not appeal to me.'],

    // ── Sensorial(a=0) / Intuitivo(b=1) ──────────────────────────────────────
    ['n' => 2,  'dim' => 'semint', 'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo',
     'enunciado' => 'I would rather be considered',
     'opcao_a'   => 'realistic.',
     'opcao_b'   => 'innovative.'],

    ['n' => 6,  'dim' => 'semint', 'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo',
     'enunciado' => 'If I were a teacher, I would rather teach a course',
     'opcao_a'   => 'that deals with facts and real life situations.',
     'opcao_b'   => 'that deals with ideas and theories.'],

    ['n' => 10, 'dim' => 'semint', 'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo',
     'enunciado' => 'I find it easier',
     'opcao_a'   => 'to learn facts.',
     'opcao_b'   => 'to learn concepts.'],

    ['n' => 14, 'dim' => 'semint', 'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo',
     'enunciado' => 'In reading nonfiction, I prefer',
     'opcao_a'   => 'something that teaches me new facts or tells me how to do something.',
     'opcao_b'   => 'something that gives me new ideas to think about.'],

    ['n' => 18, 'dim' => 'semint', 'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo',
     'enunciado' => 'I prefer the idea of',
     'opcao_a'   => 'certainty.',
     'opcao_b'   => 'theory.'],

    ['n' => 22, 'dim' => 'semint', 'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo',
     'enunciado' => 'I am more likely to be considered',
     'opcao_a'   => 'careful about the details of my work.',
     'opcao_b'   => 'creative about how to do my work.'],

    ['n' => 26, 'dim' => 'semint', 'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo',
     'enunciado' => 'When I am reading for enjoyment, I like writers to',
     'opcao_a'   => 'clearly say what they mean.',
     'opcao_b'   => 'say things in creative, interesting ways.'],

    ['n' => 30, 'dim' => 'semint', 'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo',
     'enunciado' => 'When I have to perform a task, I prefer to',
     'opcao_a'   => 'master one way of doing it.',
     'opcao_b'   => 'come up with new ways of doing it.'],

    ['n' => 34, 'dim' => 'semint', 'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo',
     'enunciado' => 'I consider it high praise to call someone',
     'opcao_a'   => 'sensible.',
     'opcao_b'   => 'imaginative.'],

    ['n' => 38, 'dim' => 'semint', 'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo',
     'enunciado' => 'I prefer courses that emphasize',
     'opcao_a'   => 'concrete material (facts, data).',
     'opcao_b'   => 'abstract material (concepts, theories).'],

    // ── Visual(a=0) / Verbal(b=1) ─────────────────────────────────────────────
    ['n' => 3,  'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'When I think about what I did yesterday, I am most likely to get',
     'opcao_a'   => 'a picture.',
     'opcao_b'   => 'words.'],

    ['n' => 7,  'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'I prefer to get new information in',
     'opcao_a'   => 'pictures, diagrams, graphs, or maps.',
     'opcao_b'   => 'written directions or verbal information.'],

    ['n' => 11, 'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'In a book with lots of pictures and charts, I am likely to',
     'opcao_a'   => 'look over the pictures and charts carefully.',
     'opcao_b'   => 'focus on the written text.'],

    ['n' => 15, 'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'I like teachers',
     'opcao_a'   => 'who put a lot of diagrams on the board.',
     'opcao_b'   => 'who spend a lot of time explaining.'],

    ['n' => 19, 'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'I remember best',
     'opcao_a'   => 'what I see.',
     'opcao_b'   => 'what I hear.'],

    ['n' => 23, 'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'When I get directions to a new place, I prefer',
     'opcao_a'   => 'a map.',
     'opcao_b'   => 'written instructions.'],

    ['n' => 27, 'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'When I see a diagram or sketch in class, I am most likely to remember',
     'opcao_a'   => 'the picture.',
     'opcao_b'   => 'what the instructor said about it.'],

    ['n' => 31, 'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'When someone is showing me data, I prefer',
     'opcao_a'   => 'charts or graphs.',
     'opcao_b'   => 'text summarizing the results.'],

    ['n' => 35, 'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'When I meet people at a party, I am more likely to remember',
     'opcao_a'   => 'what they looked like.',
     'opcao_b'   => 'what they said about themselves.'],

    ['n' => 39, 'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'For entertainment, I would rather',
     'opcao_a'   => 'watch television.',
     'opcao_b'   => 'read a book.'],

    ['n' => 43, 'dim' => 'visver', 'polo_a' => 'Visual',     'polo_b' => 'Verbal',
     'enunciado' => 'I tend to picture places I have been',
     'opcao_a'   => 'easily and fairly accurately.',
     'opcao_b'   => 'with difficulty and without much detail.'],

    // ── Sequencial(a=0) / Global(b=1) ────────────────────────────────────────
    ['n' => 4,  'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'I tend to',
     'opcao_a'   => 'understand details of a subject but may be fuzzy about its overall structure.',
     'opcao_b'   => 'understand the overall structure but may be fuzzy about the details.'],

    ['n' => 8,  'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'Once I understand',
     'opcao_a'   => 'all the parts, I understand the whole thing.',
     'opcao_b'   => 'the whole thing, I see how the parts fit.'],

    ['n' => 12, 'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'When I solve math problems',
     'opcao_a'   => 'I usually work my way to the solutions one step at a time.',
     'opcao_b'   => 'I often just see the solutions but then have to struggle to figure out the steps to get to them.'],

    ['n' => 16, 'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'When I\'m analyzing a story or a novel',
     'opcao_a'   => 'I think of the incidents and try to put them together to figure out the themes.',
     'opcao_b'   => 'I know just what the themes are when I finish reading and then I have to go back and find the incidents that demonstrate them.'],

    ['n' => 20, 'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'It is more important to me that an instructor',
     'opcao_a'   => 'lay out the material in clear sequential steps.',
     'opcao_b'   => 'give me an overall picture and relate the material to other subjects.'],

    ['n' => 24, 'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'I learn',
     'opcao_a'   => 'at a fairly regular pace. If I study hard, I\'ll "get it".',
     'opcao_b'   => 'in fits and starts. I\'ll be totally confused and then suddenly it all "clicks".'],

    ['n' => 28, 'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'When considering a body of information, I am more likely to',
     'opcao_a'   => 'focus on details and miss the big picture.',
     'opcao_b'   => 'try to understand the big picture before getting into the details.'],

    ['n' => 32, 'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'When writing a paper, I am more likely to',
     'opcao_a'   => 'work on (think about or write) the beginning of the paper and progress forward.',
     'opcao_b'   => 'work on (think about or write) different parts of the paper and then order them.'],

    ['n' => 36, 'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'When I am learning a new subject, I prefer to',
     'opcao_a'   => 'stay focused on that subject, learning as much about it as I can.',
     'opcao_b'   => 'try to make connections between that subject and related subjects.'],

    ['n' => 40, 'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'Some teachers start their lectures with an outline of what they will cover. Such outlines are',
     'opcao_a'   => 'somewhat helpful to me.',
     'opcao_b'   => 'very helpful to me.'],

    ['n' => 44, 'dim' => 'seqglo', 'polo_a' => 'Sequencial', 'polo_b' => 'Global',
     'enunciado' => 'When solving problems in a group, I would be more likely to',
     'opcao_a'   => 'think of the steps in the solution process.',
     'opcao_b'   => 'think of possible consequences or application of the solution in a wide range of areas.'],
];

// ── Configuração dos 4 questionários ─────────────────────────────────────────
$dimensoes = [
    'atiref' => ['nome' => 'ILS – Ativo / Reflexivo',      'polo_a' => 'Ativo',      'polo_b' => 'Reflexivo'],
    'semint' => ['nome' => 'ILS – Sensorial / Intuitivo',  'polo_a' => 'Sensorial',  'polo_b' => 'Intuitivo'],
    'visver' => ['nome' => 'ILS – Visual / Verbal',        'polo_a' => 'Visual',     'polo_b' => 'Verbal'],
    'seqglo' => ['nome' => 'ILS – Sequencial / Global',    'polo_a' => 'Sequencial', 'polo_b' => 'Global'],
];

// ── Garante seção no curso ────────────────────────────────────────────────────
function garantir_secao_ils(int $courseid, string $nome): stdClass {
    global $DB;
    $ex = $DB->get_record_select('course_sections', 'course = ? AND name = ?', [$courseid, $nome]);
    if ($ex) return $ex;
    $max = $DB->get_field_sql('SELECT MAX(section) FROM {course_sections} WHERE course = ?', [$courseid]);
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

// ── Cria ou reutiliza categoria de questões ───────────────────────────────────
function garantir_categoria_questoes(int $courseid, string $nome): int {
    global $DB;
    $ctx = context_course::instance($courseid);
    $ex  = $DB->get_record('question_categories', ['contextid' => $ctx->id, 'name' => $nome]);
    if ($ex) return $ex->id;
    $cat = new stdClass();
    $cat->name        = $nome;
    $cat->contextid   = $ctx->id;
    $cat->info        = '';
    $cat->infoformat  = FORMAT_HTML;
    $cat->parent      = 0;
    $cat->sortorder   = 999;
    $cat->stamp       = make_unique_id_code();
    return $DB->insert_record('question_categories', $cat);
}

// ── Cria uma questão de múltipla escolha (a/b) ───────────────────────────────
function criar_questao_ils(array $q, int $catid, int $courseid): int {
    global $DB, $USER;

    $ctx = context_course::instance($courseid);

    // Verifica se já existe pelo nome
    $existe = $DB->get_record_sql(
        "SELECT q.id FROM {question} q
         JOIN {question_versions} qv ON qv.questionid = q.id
         JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
         WHERE qbe.questioncategoryid = :cat AND q.name = :nome",
        ['cat' => $catid, 'nome' => "Q{$q['n']} – {$q['dim']}"]
    );
    if ($existe) return $existe->id;

    $now = time();

    // question_bank_entries
    $qbe = new stdClass();
    $qbe->questioncategoryid = $catid;
    $qbe->idnumber           = null;
    $qbe->ownerid            = $USER->id;
    $qbeid = $DB->insert_record('question_bank_entries', $qbe);

    // question
    $quest = new stdClass();
    $quest->category         = $catid;
    $quest->parent           = 0;
    $quest->name             = "Q{$q['n']} – {$q['dim']}";
    $quest->questiontext     = "<p>{$q['enunciado']}</p>";
    $quest->questiontextformat = FORMAT_HTML;
    $quest->generalfeedback  = '';
    $quest->generalfeedbackformat = FORMAT_HTML;
    $quest->defaultmark      = 1;
    $quest->penalty          = 0;
    $quest->qtype            = 'multichoice';
    $quest->length           = 1;
    $quest->stamp            = make_unique_id_code();
    $quest->timecreated      = $now;
    $quest->timemodified     = $now;
    $quest->createdby        = $USER->id;
    $quest->modifiedby       = $USER->id;
    $questid = $DB->insert_record('question', $quest);

    // question_versions
    $qv = new stdClass();
    $qv->questionbankentryid = $qbeid;
    $qv->version             = 1;
    $qv->questionid          = $questid;
    $qv->status              = 'ready';
    $DB->insert_record('question_versions', $qv);

    // qtype_multichoice_options
    $mc = new stdClass();
    $mc->questionid                      = $questid;
    $mc->layout                          = 0;
    $mc->single                          = 1;
    $mc->shuffleanswers                  = 0;
    $mc->correctfeedback                 = '';
    $mc->correctfeedbackformat           = FORMAT_HTML;
    $mc->partiallycorrectfeedback        = '';
    $mc->partiallycorrectfeedbackformat  = FORMAT_HTML;
    $mc->incorrectfeedback               = '';
    $mc->incorrectfeedbackformat         = FORMAT_HTML;
    $mc->answernumbering                 = 'abc';
    $mc->shownumcorrect                  = 0;
    $mc->showstandardinstruction         = 1;
    $DB->insert_record('qtype_multichoice_options', $mc);

    // answers: a=0 pontos, b=1 ponto (100%)
    foreach (['a' => 0, 'b' => 1] as $letra => $correto) {
        $ans = new stdClass();
        $ans->question   = $questid;
        $ans->answer     = $q["opcao_{$letra}"];
        $ans->answerformat = FORMAT_HTML;
        $ans->fraction   = $correto == 1 ? 1.0 : 0.0;
        $ans->feedback   = '';
        $ans->feedbackformat = FORMAT_HTML;
        $DB->insert_record('question_answers', $ans);
    }

    return $questid;
}

// ── Cria o quiz e adiciona as questões ───────────────────────────────────────
function criar_quiz(string $nome, int $courseid, int $sectionid, array $questoes_dim, int $catid): void {
    global $DB, $USER;

    $modulo = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
    $now    = time();

    // Evita duplicata
    $existe = $DB->get_record_sql(
        "SELECT cm.id FROM {course_modules} cm
         JOIN {quiz} q ON q.id = cm.instance AND cm.module = :mod
         WHERE cm.course = :course AND q.name = :nome",
        ['mod' => $modulo->id, 'course' => $courseid, 'nome' => $nome]
    );
    if ($existe) {
        echo "  [PULADO] Quiz '{$nome}' já existe\n";
        return;
    }

    // quiz
    $quiz = new stdClass();
    $quiz->course            = $courseid;
    $quiz->name              = $nome;
    $quiz->intro             = '<p>Responda cada questão escolhendo a opção que melhor descreve você.</p>';
    $quiz->introformat       = FORMAT_HTML;
    $quiz->timeopen          = 0;
    $quiz->timeclose         = 0;
    $quiz->timelimit         = 0;
    $quiz->overduehandling   = 'autosubmit';
    $quiz->graceperiod       = 0;
    $quiz->preferredbehaviour = 'deferredfeedback';
    $quiz->canredoquestions  = 0;
    $quiz->attempts          = 1;
    $quiz->attemptonlast     = 0;
    $quiz->grademethod       = 1;
    $quiz->decimalpoints     = 0;
    $quiz->questiondecimalpoints = -1;
    $quiz->reviewattempt          = 69888;
    $quiz->reviewcorrectness      = 4352;
    $quiz->reviewmaxmarks         = 4352;
    $quiz->reviewmarks            = 4352;
    $quiz->reviewspecificfeedback = 4352;
    $quiz->reviewgeneralfeedback  = 4352;
    $quiz->reviewrightanswer      = 4352;
    $quiz->reviewoverallfeedback  = 4352;
    $quiz->questionsperpage  = 0;
    $quiz->navmethod         = 'free';
    $quiz->shuffleanswers    = 0;
    $quiz->sumgrades         = count($questoes_dim);
    $quiz->grade             = count($questoes_dim); // 0-11
    $quiz->timecreated       = $now;
    $quiz->timemodified      = $now;
    $quiz->password          = '';
    $quiz->subnet            = '';
    $quiz->browsersecurity   = '-';
    $quiz->delay1            = 0;
    $quiz->delay2            = 0;
    $quiz->showuserpicture   = 0;
    $quiz->showblocks        = 0;
    $quiz->completionattemptsexhausted = 0;
    $quiz->completionminattempts = 0;
    $quiz->allowofflineattempts = 0;
    $quizid = $DB->insert_record('quiz', $quiz);

    // course_modules
    $cm = new stdClass();
    $cm->course         = $courseid;
    $cm->module         = $modulo->id;
    $cm->instance       = $quizid;
    $cm->section        = $sectionid;
    $cm->visible        = 1;
    $cm->visibleold     = 1;
    $cm->groupmode      = 0;
    $cm->groupingid     = 0;
    $cm->completion     = 2; // requer nota
    $cm->completionview = 0;
    $cm->completionexpected = 0;
    $cm->showdescription    = 0;
    $cm->added          = $now;
    $cmid = $DB->insert_record('course_modules', $cm);

    // contexto
    $course_ctx = context_course::instance($courseid);
    $ctx = new stdClass();
    $ctx->contextlevel = CONTEXT_MODULE;
    $ctx->instanceid   = $cmid;
    $ctx->parentid     = $course_ctx->id;
    $ctx->depth        = $course_ctx->depth + 1;
    $ctx->path         = '';
    $ctx->locked       = 0;
    $ctxid = $DB->insert_record('context', $ctx);
    $DB->set_field('context', 'path', $course_ctx->path . '/' . $ctxid, ['id' => $ctxid]);

    // Cria seção padrão obrigatória do quiz
    $qsec = new stdClass();
    $qsec->quizid          = $quizid;
    $qsec->firstslot       = 1;
    $qsec->heading         = '';
    $qsec->shufflequestions = 0;
    $DB->insert_record('quiz_sections', $qsec);

    // Adiciona questões ao quiz
    $ctx_quiz = context_module::instance($cmid);
    $slot = 1;
    foreach ($questoes_dim as $questao) {
        $questid = criar_questao_ils($questao, $catid, $courseid);

        $qbeid = $DB->get_field_sql(
            "SELECT qbe.id FROM {question_bank_entries} qbe
             JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
             WHERE qv.questionid = :qid",
            ['qid' => $questid]
        );

        // Insere slot primeiro (sem referência)
        $qs = new stdClass();
        $qs->quizid          = $quizid;
        $qs->slot            = $slot;
        $qs->page            = $slot; // uma questão por página
        $qs->requireprevious = 0;
        $qs->maxmark         = 1.0;
        $qs->displaynumber   = null;
        $slotid = $DB->insert_record('quiz_slots', $qs);

        // Insere question_reference apontando para o slot
        $qref = new stdClass();
        $qref->usingcontextid      = $ctx_quiz->id;
        $qref->component           = 'mod_quiz';
        $qref->questionarea        = 'slot';
        $qref->itemid              = $slotid;
        $qref->questionbankentryid = $qbeid;
        $qref->version             = null;
        $DB->insert_record('question_references', $qref);

        $slot++;
    }

    // Adiciona à sequência da seção
    $secao = $DB->get_record('course_sections', ['id' => $sectionid]);
    $seq   = $secao->sequence ? $secao->sequence . ',' . $cmid : (string)$cmid;
    $DB->set_field('course_sections', 'sequence', $seq, ['id' => $sectionid]);

    // grade_items para o quiz
    $gi = new stdClass();
    $gi->courseid     = $courseid;
    $gi->categoryid   = null;
    $gi->itemname     = $nome;
    $gi->itemtype     = 'mod';
    $gi->itemmodule   = 'quiz';
    $gi->iteminstance = $quizid;
    $gi->itemnumber   = 0;
    $gi->idnumber     = '';
    $gi->gradetype    = 1;
    $gi->grademax     = count($questoes_dim);
    $gi->grademin     = 0;
    $gi->gradepass    = 0;
    $gi->multfactor   = 1.0;
    $gi->plusfactor   = 0.0;
    $gi->timecreated  = $now;
    $gi->timemodified = $now;
    $DB->insert_record('grade_items', $gi);

    $total_questoes = $slot - 1;
    echo "  [OK] Quiz '{$nome}' criado — cmid={$cmid}, {$total_questoes} questões\n";
}

// ── Execução principal ────────────────────────────────────────────────────────
echo "Criando questionários ILS no curso " . COURSE_ID . "\n\n";

$secao = garantir_secao_ils(COURSE_ID, 'Questionário de Estilo de Aprendizagem (ILS)');
$catid = garantir_categoria_questoes(COURSE_ID, 'ILS – Felder-Silverman');

foreach ($dimensoes as $dim_key => $dim_info) {
    echo "══ {$dim_info['nome']} ══\n";

    $questoes_dim = array_filter($questoes, fn($q) => $q['dim'] === $dim_key);
    usort($questoes_dim, fn($a, $b) => $a['n'] - $b['n']);

    criar_quiz($dim_info['nome'], COURSE_ID, $secao->id, $questoes_dim, $catid);
    echo "\n";
}

rebuild_course_cache(COURSE_ID, true);

echo "═══════════════════════════════════════════════════════\n";
echo "Concluído. Próximo passo: criar Items Calculados no Gradebook\n";
echo "com a fórmula =( [[quiz_id]] * 2 ) - 11 para cada dimensão.\n";
