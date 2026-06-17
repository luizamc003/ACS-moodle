<?php
/**
 * Importa TODOS os dados do banco TCC para o Moodle.
 * Cria usuários Moodle + campos de perfil personalizados com dados TCC.
 *
 * Pré-requisito: banco introducao_a_computacao já importado no MySQL.
 *   sudo php /home/luiza/Desktop/TCC/inserir_dados.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');
require_once($CFG->dirroot . '/user/lib.php');

global $DB, $CFG;

// ─── Conexão separada com o banco do TCC ──────────────────────────────────────
$tcc = new mysqli('localhost', 'root', 'senha', 'introducao_a_computacao');
if ($tcc->connect_error) {
    die("Erro ao conectar no banco do TCC: " . $tcc->connect_error . "\n");
}
$tcc->set_charset('utf8');

// ─── Funções auxiliares ────────────────────────────────────────────────────────

function garantir_categoria_perfil($nome)
{
    global $DB;
    $cat = $DB->get_record('user_info_category', ['name' => $nome]);
    if ($cat) {
        return $cat->id;
    }
    $novo = new stdClass();
    $novo->name = $nome;
    $novo->sortorder = $DB->count_records('user_info_category') + 1;
    return $DB->insert_record('user_info_category', $novo);
}

function garantir_campo_perfil($shortname, $nome, $tipo, $catid, $sortorder)
{
    global $DB;
    $campo = $DB->get_record('user_info_field', ['shortname' => $shortname]);
    if ($campo) {
        return $campo->id;
    }
    $novo = new stdClass();
    $novo->shortname = $shortname;
    $novo->name = $nome;
    $novo->datatype = $tipo;
    $novo->description = '';
    $novo->descriptionformat = 1;
    $novo->categoryid = $catid;
    $novo->sortorder = $sortorder;
    $novo->required = 0;
    $novo->locked = 0;
    $novo->visible = 2;
    $novo->forceunique = 0;
    $novo->signup = 0;
    $novo->defaultdata = ($tipo === 'checkbox') ? '0' : '';
    $novo->defaultdataformat = 0;

    if ($tipo === 'text') {
        $novo->param1 = '0';  // 0 = sem limite de caracteres
        $novo->param2 = '50'; // largura de exibição
    } elseif ($tipo === 'textarea') {
        $novo->param1 = '30'; // colunas
        $novo->param2 = '10'; // linhas
    } else {
        $novo->param1 = '';
        $novo->param2 = '';
    }
    $novo->param3 = '';
    $novo->param4 = '';
    $novo->param5 = '';

    return $DB->insert_record('user_info_field', $novo);
}

function salvar_campo_perfil($userid, $fieldid, $valor)
{
    global $DB;
    $existente = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid]);
    if ($existente) {
        $existente->data = (string) $valor;
        $DB->update_record('user_info_data', $existente);
    } else {
        $dado = new stdClass();
        $dado->userid = $userid;
        $dado->fieldid = $fieldid;
        $dado->data = (string) $valor;
        $dado->dataformat = 0;
        $DB->insert_record('user_info_data', $dado);
    }
}

// ─── Garante categoria e campos de perfil ─────────────────────────────────────
echo "Preparando campos de perfil personalizados...\n";

$catid = garantir_categoria_perfil('Dados TCC');

// [shortname => [nome exibido, tipo, ordem]]
$definicoes_campos = [
    'matricula_tcc' => ['Matrícula (TCC)', 'text', 1],
    'curso_tcc' => ['Curso', 'text', 2],
    'tempo_de_curso' => ['Tempo de Curso', 'text', 3],
    'experiencia_ti' => ['Experiência em TI', 'checkbox', 4],
    'habilidade_ti' => ['Habilidade em TI (índice)', 'text', 5],
    'descricao_exp' => ['Descrição da Experiência', 'textarea', 6],
    'tempo_disp_min' => ['Tempo Disponível Mínimo (h)', 'text', 7],
    'tempo_disp_max' => ['Tempo Disponível Máximo (h)', 'text', 8],
    'grupo_tcc' => ['Grupo TCC', 'text', 9],
    'estilo_atiref' => ['Estilo: Ativo/Reflexivo', 'text', 10],
    'estilo_semint' => ['Estilo: Sensorial/Intuitivo', 'text', 11],
    'estilo_visver' => ['Estilo: Visual/Verbal', 'text', 12],
    'estilo_seqglo' => ['Estilo: Sequencial/Global', 'text', 13],
];

$field_ids = [];
foreach ($definicoes_campos as $shortname => [$nome, $tipo, $ordem]) {
    $field_ids[$shortname] = garantir_campo_perfil($shortname, $nome, $tipo, $catid, $ordem);
}

echo "  OK — " . count($field_ids) . " campos prontos na categoria \"Dados TCC\".\n\n";

// ─── Consulta alunos + estilos de aprendizagem ────────────────────────────────
$sql = "
    SELECT a.*,
           e.atiref, e.semint, e.visver, e.seqglo
    FROM aluno a
    LEFT JOIN estilo_de_aprendizagem e ON e.id_aluno = a.id
    WHERE a.email != 'teste@teste'
      AND TRIM(a.email) != ''
    ORDER BY a.id
";
$result = $tcc->query($sql);
if (!$result) {
    die("Erro na consulta TCC: " . $tcc->error . "\n");
}

$criados = 0;
$pulados = 0;
$erros = 0;

while ($aluno = $result->fetch_object()) {
    $username = strtolower(trim($aluno->matricula));
    $email = strtolower(trim($aluno->email));

    // ── Verifica se já existe ─────────────────────────────────────────────────
    $userid = null;

    $user_por_username = $DB->get_record('user', ['username' => $username]);
    $user_por_email = $DB->get_record('user', ['email' => $email]);

    if ($user_por_username || $user_por_email) {
        $existente = $user_por_username ?: $user_por_email;
        $userid = $existente->id;
        echo "  [PULADO] {$username} ({$email}) — já existe (ID={$userid}), atualizando perfil.\n";
        $pulados++;
    } else {
        // ── Cria o usuário no Moodle ──────────────────────────────────────────
        $partes = explode(' ', trim($aluno->nome), 2);
        $firstname = ucwords(strtolower($partes[0]));
        $lastname = isset($partes[1]) && trim($partes[1]) !== ''
            ? ucwords(strtolower($partes[1]))
            : '.';

        $novo = new stdClass();
        $novo->username = $username;
        $novo->password = 'Senha.123';
        $novo->firstname = $firstname;
        $novo->lastname = $lastname;
        $novo->email = $email;
        $novo->phone1 = $aluno->telefone ?? '';
        $novo->description = $aluno->descricao_experiencia ?? '';
        $novo->country = 'BR';
        $novo->auth = 'manual';
        $novo->confirmed = 1;
        $novo->mnethostid = $CFG->mnet_localhost_id;
        $novo->lang = 'pt_br';

        try {
            $userid = user_create_user($novo, true, false);
            echo "  [OK] {$aluno->nome} → username={$username}, ID={$userid}\n";
            $criados++;
        } catch (Exception $e) {
            echo "  [ERRO] {$username}: " . $e->getMessage() . "\n";
            $erros++;
            continue;
        }
    }

    // ── Salva todos os campos de perfil TCC ───────────────────────────────────
    salvar_campo_perfil($userid, $field_ids['matricula_tcc'], $aluno->matricula ?? '');
    salvar_campo_perfil($userid, $field_ids['curso_tcc'], $aluno->curso ?? '');
    salvar_campo_perfil($userid, $field_ids['tempo_de_curso'], $aluno->tempo_de_curso ?? '');
    salvar_campo_perfil($userid, $field_ids['experiencia_ti'], $aluno->experiencia ?? 0);
    salvar_campo_perfil($userid, $field_ids['habilidade_ti'], $aluno->habilidade ?? '');
    salvar_campo_perfil($userid, $field_ids['descricao_exp'], $aluno->descricao_experiencia ?? '');
    salvar_campo_perfil($userid, $field_ids['tempo_disp_min'], $aluno->tempo_disponivel_min ?? '');
    salvar_campo_perfil($userid, $field_ids['tempo_disp_max'], $aluno->tempo_disponivel_max ?? '');
    salvar_campo_perfil($userid, $field_ids['grupo_tcc'], $aluno->identificador_grupo ?? '');
    salvar_campo_perfil($userid, $field_ids['estilo_atiref'], $aluno->atiref ?? '');
    salvar_campo_perfil($userid, $field_ids['estilo_semint'], $aluno->semint ?? '');
    salvar_campo_perfil($userid, $field_ids['estilo_visver'], $aluno->visver ?? '');
    salvar_campo_perfil($userid, $field_ids['estilo_seqglo'], $aluno->seqglo ?? '');
}

$tcc->close();

echo "\n─────────────────────────────────────────────────────────────────\n";
echo "Concluído — Criados: {$criados} | Pulados/Atualizados: {$pulados} | Erros: {$erros}\n";
