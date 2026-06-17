# Scripts PHP — TCC Moodle

Todos os scripts são executados via CLI com `sudo php <caminho_do_script>`.
O Moodle deve estar em `/var/www/html/moodle` e o banco MySQL acessível.

---

## Ordem de execução recomendada

```
1. inserir_dados.php
2. criar_scorms.php  →  criar_scorms_lom.php
3. corrigir_scoes_org.php
4. competencias.php
5. criar_competencias_estilo.php
6. associar_scorm_competencias.php
7. associar_scorm_tags_dificuldade.php
8. popular_tempo_estimado.php
9. criar_learning_plans.php
10. criar_questionario_ils.php
11. sincronizar_estilo_perfil.php  (rodar após alunos responderem)
```

---

## Scripts de configuração inicial

### `inserir_dados.php`

Importa alunos e seus estilos de aprendizagem do banco `introducao_a_computacao`
para o Moodle. Cria contas de usuário e preenche os campos de perfil
(`estilo_atiref`, `estilo_semint`, `estilo_visver`, `estilo_seqglo`).

Utilizei apenas a importação dos alunos para o banco.

```bash
sudo php /home/luiza/Desktop/TCC/inserir_dados.php
```

---

### `criar_scorms_lom.php`

Versão completa de criação de SCORMs. Lê os arquivos XML LOM de cada
disciplina, gera o `imsmanifest.xml` com metadados embutidos (título,
descrição, dificuldade, tempo estimado, keywords) e cria os SCORMs no
Moodle organizados por seção/disciplina.

```bash
sudo php /home/luiza/Desktop/TCC/criar_scorms_lom.php
```

**Pré-requisito:** pastas com XMLs LOM em `/home/luiza/Desktop/TCC/<disciplina>/`.

---

### `corrigir_scoes_org.php`

Corrige SCORMs que foram criados sem o SCO de organização (registro pai).
Sem esse registro o player do Moodle quebra. Deve ser rodado se os SCORMs
aparecerem com erro ao abrir.

```bash
sudo php /home/luiza/Desktop/TCC/corrigir_scoes_org.php
```

---

## Scripts de competências

### `competencias.php`

Cria a escala de avaliação (Insuficiente → Expert), o framework "Conceitos TCC"
e uma competência por conceito do banco `introducao_a_computacao`. Também
associa as competências ao curso e insere as habilidades dos alunos por conceito
em `competency_usercomp`.

```bash
sudo php /home/luiza/Desktop/TCC/competencias.php
```

---

### `criar_competencias_estilo.php`

Cria o framework "Estilo de Aprendizado" com escala bipolar de −11 a +11
e 4 subcompetências baseadas no modelo de Felder-Silverman:

- Ativo/Reflexivo
- Sensorial/Intuitivo
- Visual/Verbal
- Sequencial/Global

```bash
sudo php /home/luiza/Desktop/TCC/criar_competencias_estilo.php
```

---

### `associar_scorm_competencias.php`

Lê os CSVs de mapeamento `material_id → siglas de conceito` e associa cada
SCORM às competências correspondentes em `competency_modulecomp`.

```bash
sudo php /home/luiza/Desktop/TCC/associar_scorm_competencias.php
```

**Pré-requisito:** `competencias.php` e `criar_scorms_lom.php` já executados.

---

### `criar_learning_plans.php`

Cria Learning Plans individuais para cada aluno no Moodle e vincula suas
competências ao plano. Gera os registros em `competency_plan`,
`competency_plancomp` e `competency_usercompplan`.

```bash
sudo php /home/luiza/Desktop/TCC/criar_learning_plans.php
```

**Pré-requisito:** `competencias.php` já executado.

---

## Scripts de metadados LOM

### `associar_scorm_tags_dificuldade.php`

Lê o campo `<difficulty>` de cada XML LOM e associa a tag correspondente
ao SCORM no Moodle usando a API nativa de tags (`core_tag_tag::set_item_tags`).

As 5 tags devem existir previamente no Moodle:
`Very Easy`, `Easy`, `Medium`, `Difficult`, `Very Difficult`

```bash
sudo php /home/luiza/Desktop/TCC/associar_scorm_tags_dificuldade.php
```

---

### `popular_tempo_estimado.php`

Lê o campo `<typicalLearningTime>` de cada XML LOM, converte de ISO 8601
(ex: `PT28M18S`) para minutos arredondados e salva na tabela
`mdl_local_scorm_lom` (criada pelo plugin `local_scorm_lom`).

```bash
sudo php /home/luiza/Desktop/TCC/popular_tempo_estimado.php
```

**Pré-requisito:** plugin `local_scorm_lom` instalado.

---

## Scripts do questionário ILS

### `criar_questionario_ils.php`

Cria os 4 questionários do Index of Learning Styles (Felder-Silverman)
no Moodle com as 44 questões originais, divididas por dimensão:

- ILS – Ativo / Reflexivo (11 questões)
- ILS – Sensorial / Intuitivo (10 questões)
- ILS – Visual / Verbal (11 questões)
- ILS – Sequencial / Global (11 questões)

Cada questionário tem nota de 0 a 11 (1 ponto por resposta "b").
A conversão para a escala −11 a +11 é: `(2 × nota) − 11`.

```bash
sudo php /home/luiza/Desktop/TCC/criar_questionario_ils.php
```

---

### `sincronizar_estilo_perfil.php`

Lê as notas finais dos 4 quizzes ILS, aplica a fórmula `(2 × nota) − 11`
e salva o resultado nos campos de perfil do aluno
(`estilo_atiref`, `estilo_semint`, `estilo_visver`, `estilo_seqglo`).

Deve ser rodado após os alunos responderem os questionários.

```bash
sudo php /home/luiza/Desktop/TCC/sincronizar_estilo_perfil.php
```

---

## Scripts utilitários / diagnóstico

### `apagar_secoes_material.php`

Remove seções vazias com nome "Material \*" ou "Materiais" do curso.
Aceita `--dry-run` para listar sem apagar.

```bash
sudo php /home/luiza/Desktop/TCC/apagar_secoes_material.php
sudo php /home/luiza/Desktop/TCC/apagar_secoes_material.php --dry-run
```

---

### `listar_competencias.php`

Lista todos os frameworks e competências existentes no Moodle com seus
IDs e idnumbers. Útil para verificar o estado após rodar os scripts
de competências.

```bash
sudo php /home/luiza/Desktop/TCC/listar_competencias.php
```

---

### `inspecionar_tabela.php`

Exibe as colunas das tabelas de associação SCORM-competência
(`competency_coursemodulecomp`, `competency_modulecomp`).
Script de diagnóstico usado durante o desenvolvimento.

```bash
sudo php /home/luiza/Desktop/TCC/inspecionar_tabela.php
```

---

### `main.php` / `scorm.php`

Scripts exploratórios usados durante o desenvolvimento para inspecionar
dados do SCORM e metadados XML diretamente no banco. Não fazem alterações.

---

## Arquivos de suporte

| Arquivo               | Descrição                                                                                                                                |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `plugin_scorm_lom/`   | Plugin local do Moodle que adiciona campo "Tempo estimado" no formulário de SCORM e observer automático para salvar estilo após quiz ILS |
| `*.csv`               | Dados dos materiais por disciplina com mapeamento de conceitos                                                                           |
| `<disciplina>/*.xml`  | Metadados LOM de cada material                                                                                                           |
| `dados_tcc_final.sql` | Dump do banco `introducao_a_computacao`                                                                                                  |
