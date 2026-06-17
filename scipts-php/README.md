# scipts-php — Scripts de Configuração do Moodle

Scripts PHP e Python utilizados para popular e configurar o ambiente Moodle do TCC. Todos os scripts PHP são executados via CLI com `sudo php <caminho>` e requerem acesso ao Moodle instalado em `/var/www/html/moodle`.

---

## Ordem de execução recomendada

```
1. inserir_dados_alunos.php
2. criar_scorms_lom.php
3. corrigir_scoes_org.php          (se necessário)
4. competencias.php
5. associar_scorm_competencias.php
6. associar_scorm_tags_dificuldade.php
7. popular_tempo_estimado.php
8. criar_learning_plans.php
9. criar_questionario_ils.php
10. sincronizar_estilo_perfil.php   (após alunos responderem o ILS) -- nao utilizado
```

---

## Scripts PHP

### `inserir_dados_alunos.php`

Importa todos os alunos do banco `introducao_a_computacao` para o Moodle. Cria contas de usuário e cria campos de perfil personalizados com os dados do TCC (estilo de aprendizagem, habilidades por conceito, tempo de estudo).

```bash
sudo php inserir_dados_alunos.php
```

**Pré-requisito:** banco MySQL `introducao_a_computacao` importado a partir de `database/dados_tcc.sql`.

---

### `criar_scorms_lom.php`

Lê cada arquivo XML LOM da pasta `LOM-disciplina/`, gera um `imsmanifest.xml` com os metadados embutidos (título, descrição, dificuldade, tempo estimado, keywords) e cria os SCORMs no Moodle organizados por seção/disciplina dentro do curso ID 2.

```bash
sudo php criar_scorms_lom.php
```

**Pré-requisito:** pasta `LOM-disciplina/` com os XMLs de cada disciplina.

---

### `competencias.php`

Configura todo o framework de competências no Moodle:

1. Cria a escala "Escala TCC (1-5)": Insuficiente → Básico → Intermediário → Avançado → Expert
2. Cria o framework "Conceitos TCC"
3. Cria uma competência por conceito do banco TCC
4. Associa cada competência ao curso
5. Insere a habilidade de cada aluno por conceito em `competency_usercomp`

```bash
sudo php competencias.php
```

**Pré-requisito:** `inserir_dados_alunos.php` já executado.

---

### `associar_scorm_competencias.php`

Lê CSVs de mapeamento `material_id → siglas de conceito` e associa cada SCORM às competências correspondentes na tabela `competency_modulecomp`.

```bash
sudo php associar_scorm_competencias.php
```

**Pré-requisito:** `competencias.php` e `criar_scorms_lom.php` já executados.

---

### `associar_scorm_tags_dificuldade.php`

Percorre os XMLs LOM de cada disciplina, extrai o campo `<difficulty>` e associa a tag correspondente ao SCORM no Moodle usando a API nativa de tags.

As 5 tags devem existir previamente no Moodle: `Very Easy`, `Easy`, `Medium`, `Difficult`, `Very Difficult`.

```bash
sudo php associar_scorm_tags_dificuldade.php
```

---

### `popular_tempo_estimado.php`

Lê o campo `<typicalLearningTime>` dos XMLs LOM (formato ISO 8601, ex: `PT28M18S`), converte para minutos e salva na tabela `mdl_local_scorm_lom` (criada pelo plugin `local_scorm_lom`).

```bash
sudo php popular_tempo_estimado.php
```

**Pré-requisito:** plugin `local_scorm_lom` instalado (ver `plugin-material-duration/`).

---

### `criar_learning_plans.php`

Cria um Learning Plan individual para cada aluno no Moodle e vincula todas as suas competências ao plano. Gera registros em `competency_plan`, `competency_plancomp`, `competency_usercompplan` e `competency_usercompcourse`.

```bash
sudo php criar_learning_plans.php
```

**Pré-requisito:** `competencias.php` já executado.

---

### `criar_questionario_ils.php`

Cria os 4 questionários do **Index of Learning Styles (ILS)** de Felder-Silverman no Moodle, com as 44 questões originais divididas por dimensão:

- ILS – Ativo / Reflexivo (11 questões)
- ILS – Sensorial / Intuitivo (10 questões)
- ILS – Visual / Verbal (11 questões)
- ILS – Sequencial / Global (11 questões)

Cada questionário tem nota de 0 a 11 (1 ponto por resposta "b"). A conversão para a escala −11 a +11 é feita pela fórmula `(2 × nota) − 11`.

```bash
sudo php criar_questionario_ils.php
```

---

## Scripts Python

### `exportar_conceitos_disciplinas.py`

Lê o arquivo SQL do banco TCC e gera o CSV `conceitos_por_disciplina.csv` com as disciplinas nas linhas e seus conceitos nas colunas. Não requer conexão com banco de dados.

```bash
python3 exportar_conceitos_disciplinas.py
```

---

### `extrair_conceitos_moodle.py`

Extrai a lista de conceitos (sigla + nome) e, opcionalmente, cruza com as competências cadastradas no Moodle via Web Services API. Gera `conceitos.csv`.

```bash
# Sem conexão Moodle (apenas dados locais)
python3 extrair_conceitos_moodle.py

# Com conexão Moodle
python3 extrair_conceitos_moodle.py --url https://seu-moodle.com --token SEU_TOKEN
```

---

### `extrair_learner_scores.py`

Lê o dump SQL e gera `learner_scores.csv` com a habilidade de cada aluno por conceito.

```
id_aluno;sigla_conceito;habilidade
```

```bash
python3 extrair_learner_scores.py
python3 extrair_learner_scores.py --sql database/dados_tcc.sql --output learner_scores.csv
```

---

### `extrair_learners.py`

Lê o dump SQL e gera `learners.csv` com os perfis dos alunos no formato exigido pelas instâncias ACS: tempo mínimo/máximo de estudo, estilo de aprendizagem (ILS) e objetivos de aprendizagem (conceitos marcados como 1 para todos).

```
id;tempo_min;tempo_max;ativo_reflexivo;sensorial_intuitivo;visual_verbal;sequencial_global;ICHCC01;...
```

```bash
python3 extrair_learners.py
python3 extrair_learners.py --sql database/dados_tcc.sql --output learners.csv
```

---

### `extrair_sequencia_materiais.py`

Extrai do dump SQL a sequência de materiais recebida por cada aluno do **grupo 1** (sequenciamento adaptado), por disciplina. Gera o CSV usado como baseline de comparação no ACS.

```bash
python3 extrair_sequencia_materiais.py
python3 extrair_sequencia_materiais.py --sql database/dados_tcc.sql --output sequencia_materiais.csv
```

---

## Subpasta `scripts-aux-limpeza/`

Scripts auxiliares e de diagnóstico usados durante o desenvolvimento. Não fazem parte do fluxo principal.

### `corrigir_scoes_org.php`

Corrige SCORMs criados sem o SCO de organização (registro pai na tabela `scorm_scoes`). Sem esse registro, o player do Moodle falha com `array_keys(null)`.

```bash
sudo php scripts-aux-limpeza/corrigir_scoes_org.php
```

### `apagar_secoes_material.php`

Remove seções vazias com nome "Material \*" ou "Materiais" do curso. Suporta `--dry-run` para listar sem apagar.

```bash
sudo php scripts-aux-limpeza/apagar_secoes_material.php --dry-run
sudo php scripts-aux-limpeza/apagar_secoes_material.php
```

### `listar_competencias.php`

Lista todos os frameworks e competências existentes no Moodle com seus IDs. Útil para verificar o estado após rodar `competencias.php`.

```bash
sudo php scripts-aux-limpeza/listar_competencias.php
```

### `inspecionar_tabela.php`

Exibe as colunas das tabelas de associação SCORM–competência (`competency_coursemodulecomp`, `competency_modulecomp`). Script de diagnóstico.

```bash
sudo php scripts-aux-limpeza/inspecionar_tabela.php
```

### `sincronizar_estilo_perfil.php`

_(Não utilizado no fluxo final.)_ Lê as notas dos 4 quizzes ILS, aplica `(2 × nota) − 11` e salva o resultado nos campos de perfil do aluno. Deve ser rodado após os alunos responderem o questionário.

```bash
sudo php scripts-aux-limpeza/sincronizar_estilo_perfil.php
```
