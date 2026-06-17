# Plugin local_scorm_lom — Tempo Estimado do Material

Plugin local do Moodle que adiciona o campo **Tempo estimado (min)** no formulário de criação e edição de atividades SCORM.

O valor do tempo estimado é salvo na tabela `mdl_local_scorm_lom` e pré-preenchido automaticamente ao editar um material já cadastrado.

---

## Estrutura do plugin

```
plugin-material-duration/
├── version.php                 — versão e metadados do plugin
├── lib.php                     — hooks de formulário (exibição, validação e salvamento)
├── classes/
│   └── observer.php            — observer de evento: atualiza estilo após quiz ILS
├── db/
│   ├── install.xml             — criação da tabela mdl_local_scorm_lom
│   └── events.php              — registro do observer de eventos
└── lang/
    └── en/
        └── local_scorm_lom.php — strings de idioma do plugin
```

---

## Arquivos

### `lib.php`

Implementa três hooks do Moodle:

- **`local_scorm_lom_coursemodule_standard_elements`** — adiciona o campo "Tempo estimado (min)" no formulário de edição de SCORM, pré-populando com o valor já salvo.
- **`local_scorm_lom_coursemodule_validation`** — valida que o valor informado está entre 0 e 9999.
- **`local_scorm_lom_coursemodule_edit_post_actions`** — salva ou atualiza o tempo estimado na tabela `mdl_local_scorm_lom` após criar ou editar o SCORM.

# NÃO UTILIZADO NO EXPERIMENTO

Foi utilizado apenas a ideia de associar o tempo aos materiais e não o cálculo do estilo de aprendizado. O estilo de aprendizado foi proposto a partir de questionários e o cálculo do estilo de aprendizado pode ser feito no script ACS.

### `classes/observer.php`

Observer do evento `mod_quiz\event\attempt_submitted`. Ao detectar a submissão de um dos 4 quizzes ILS (identificados pelos `grade_item.id` 12–15), lê a nota bruta, aplica a fórmula `(2 × nota) − 11` e salva o resultado no campo de perfil correspondente do aluno (`estilo_atiref`, `estilo_semint`, `estilo_visver`, `estilo_seqglo`).

### `db/install.xml`

Define a tabela `mdl_local_scorm_lom` criada na instalação:

| Coluna           | Tipo | Descrição                      |
| ---------------- | ---- | ------------------------------ |
| `id`             | INT  | Chave primária                 |
| `cmid`           | INT  | ID do `course_modules` (único) |
| `estimated_time` | INT  | Tempo estimado em minutos      |

### `db/events.php`

Registra o observer `local_scorm_lom\observer::quiz_attempt_submitted` para o evento `\mod_quiz\event\attempt_submitted`.

---

## Pré-requisitos

- Moodle 4.0 ou superior
- PHP 8.0 ou superior
- Acesso `sudo` ao servidor

---

## Instalação

### 1. Verificar `max_input_vars` do PHP

```bash
grep "max_input_vars" /etc/php/8.4/cli/php.ini
```

Se estiver abaixo de 5000, corrija:

```bash
sudo sed -i 's/^;max_input_vars = 1000/max_input_vars = 5000/' /etc/php/8.4/cli/php.ini
```

### 2. Copiar o plugin para o Moodle

```bash
sudo cp -r /home/luiza/ACS-moodle/plugin-material-duration /var/www/html/moodle/public/local/scorm_lom
```

### 3. Ajustar permissões

```bash
sudo chown -R www-data:www-data /var/www/html/moodle/public/local/scorm_lom
```

### 4. Instalar via CLI

```bash
sudo php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive
```

A saída esperada inclui `-->local_scorm_lom` seguido de `Upgrade completed successfully`.

---

## Verificação

Após instalar, edite qualquer atividade SCORM no Moodle. Uma nova seção **"Metadados LOM do SCORM"** deve aparecer no formulário com o campo **Tempo estimado (min)**.

---

## Desinstalação

**Via interface (recomendado):**
Administração do site → Plugins → Visão geral dos plugins → Local plugins → Metadados LOM do SCORM → Desinstalar

**Via CLI:**

```bash
sudo rm -rf /var/www/html/moodle/public/local/scorm_lom
sudo php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive
```
