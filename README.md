# ACS-moodle — TCC: Sequenciamento Curricular Adaptivo no Moodle

Repositório do Trabalho de Conclusão de Curso sobre **Sequenciamento Curricular Adaptivo (ACS)** aplicado a um curso de Introdução à Computação no Moodle. O projeto cobre desde a preparação do ambiente Moodle até a execução e comparação do algoritmo genético de sequenciamento.

Em cada pasta do repositório, existe um README.md com as especificidades de execução e explicação dos scripts.

---

## Estrutura do repositório

```
ACS-moodle/
├── ACS/                        — scripts do experimento ACS (comparação de sequenciamentos)
├── LOM-disciplina/             — metadados LOM (XML) de todos os materiais didáticos
├── database/                   — dump SQL, mapeamentos de conceitos e sequências de referência
├── plugin-material-duration/   — plugin local do Moodle (tempo estimado + observer ILS)
└── scipts-php/                 — scripts PHP/Python para configurar o Moodle
```

---

## Visão geral

O experimento compara duas abordagens de sequenciamento de materiais para alunos de Introdução à Computação:

- **Sequenciamento adaptativo (GA)** — gerado pelo Algoritmo Genético do framework ACS, personalizado por perfil de aluno (estilo de aprendizagem, habilidade por conceito, tempo disponível).
- **Sequenciamento de referência** — sequência gerada por experimentos anteriores, registrada em `database/sequencia_materiais-MARCELO.csv`.

O ambiente Moodle foi configurado com os materiais, competências, questionários ILS e Learning Plans dos alunos. O algoritmo ACS então processa as instâncias e gera sequências otimizadas.

---

## Pastas

### [ACS/](ACS/README.md)

Scripts auxiliares para rodar o experimento de comparação. O principal é `comparar_sequencias.py`, que executa o GA N vezes por disciplina e compara o fitness médio com o sequenciamento de referência.

**Deve ser executado a partir do diretório raiz do repositório ACS.**

---

### [LOM-disciplina/](LOM-disciplina/README.md)

Arquivos XML com metadados no padrão IEEE LOM para cada material didático das 6 disciplinas do curso:

| Disciplina                                  | Sigla ACS | Materiais |
| ------------------------------------------- | --------- | --------- |
| Algorithm                                   | ICFA      | 39        |
| Computer Network                            | ICRC      | 38        |
| Database And Software Engineering           | ICFBDES   | 58        |
| History of Computation And Computer History | ICHCC     | 62        |
| Numeric System And Logic                    | ICLSN     | 56        |
| Operation System And Computer Organization  | ICFSOOC   | 31        |

Os XMLs são a fonte de dados para criação dos SCORMs no Moodle e para as instâncias do algoritmo ACS.

---

### [database/](database/README.md)

Dados do experimento:

| Arquivo                           | Conteúdo                                                                |
| --------------------------------- | ----------------------------------------------------------------------- |
| `dados_tcc.sql`                   | Dump MySQL com alunos, conceitos, habilidades e estilos de aprendizagem |
| `conceitos.csv`                   | Mapeamento sigla → nome de cada conceito                                |
| `conceitos_por_disciplina.csv`    | Quais conceitos pertencem a cada disciplina                             |
| `sequencia_materiais-MARCELO.csv` | Sequências do grupo de controle (baseline de comparação)                |
| `Index_of_Learning_Styles.pdf`    | Formulário ILS original de Felder-Silverman                             |

---

### [plugin-material-duration/](plugin-material-duration/README.md)

Plugin local do Moodle (`local_scorm_lom`) com duas responsabilidades:

1. Adiciona o campo **Tempo estimado (min)** no formulário de edição de SCORMs.
2. Atualiza automaticamente o estilo de aprendizagem do aluno nos campos de perfil após a submissão de qualquer um dos 4 quizzes ILS.

**Instalação:**

```bash
sudo cp -r plugin-material-duration /var/www/html/moodle/public/local/scorm_lom
sudo chown -R www-data:www-data /var/www/html/moodle/public/local/scorm_lom
sudo php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive
```

---

### [scipts-php/](scipts-php/README.md)

Scripts PHP e Python para configurar o Moodle.

---

## Pré-requisitos

- Moodle 4.0+ em `/var/www/html/moodle`
- PHP 8.0+ com `max_input_vars >= 5000`
- MySQL com o banco `introducao_a_computacao` importado
- Python 3.10+ com `numpy` instalado
- Repositório ACS com os módulos `acs/`, `algorithms/` e `utils/`. Disponível em: https://github.com/lapic-ufjf/evolutionary-ACS-benchmark

---

## Fluxo completo

```
dados_tcc.sql
     │
     ├─ scipts-php/inserir_dados_alunos.php   → usuários Moodle
     ├─ scipts-php/extrair_learners.py        → learners.csv  ┐
     ├─ scipts-php/extrair_learner_scores.py  → learner_scores.csv ┤→ instances/luiza/
     └─ scipts-php/extrair_sequencia_materiais.py → sequencia_materiais.csv ┘

LOM-disciplina/*.xml
     │
     ├─ scipts-php/criar_scorms_lom.php       → SCORMs no Moodle
     ├─ scipts-php/popular_tempo_estimado.php → tempo estimado (plugin)
     └─ (base para instâncias ACS)

ACS/comparar_sequencias.py
     │
     ├─ executa GA N vezes por disciplina
     ├─ results/experimento_luiza/<disciplina>/rodada_N.csv
     └─ results/comparacao.csv  ← comparativo final
```
