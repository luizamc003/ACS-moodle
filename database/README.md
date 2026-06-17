# database — Dados do TCC

Arquivos de dados utilizados no TCC: dump do banco, mapeamentos de conceitos e sequências de materiais geradas pelo algoritmo de referência.

---

## Arquivos

### `dados_tcc.sql`

Dump MySQL do banco `introducao_a_computacao` com todos os dados do experimento.

Tabelas relevantes:

| Tabela                       | Conteúdo                                                               |
| ---------------------------- | ---------------------------------------------------------------------- |
| `mld_aluno` / `aluno`        | Dados dos participantes (nome, matrícula, tempo_min, tempo_max, grupo) |
| `conceito`                   | Sigla e nome de cada conceito (ex: `ICHCC01 – Histórico`)              |
| `disciplina`                 | Sigla e nome de cada disciplina                                        |
| `aluno_conceito`             | Habilidade de cada aluno por conceito (escala 1–5)                     |
| `estilo_de_aprendizagem`     | Estilo ILS de cada aluno (atiref, semint, visver, seqglo)              |
| `aluno_disciplina_materiais` | Sequência de materiais recebida por cada aluno por disciplina          |

Usado pelos scripts Python de extração (`extrair_learners.py`, `extrair_learner_scores.py`, `extrair_sequencia_materiais.py`) e pelos scripts PHP de importação para o Moodle.

---

### `conceitos.csv`

Mapeamento simples entre sigla e nome de cada conceito do curso.

```
sigla,nome
ICHCC01,Histórico
ICHCC02,Gerações de Computadores
...
```

Gerado por `exportar_conceitos_disciplinas.py` e usado como referência nos scripts de associação de competências.

---

### `conceitos_por_disciplina.csv`

Mapeamento de quais conceitos pertencem a cada disciplina, no formato de tabela cruzada.

```
Disciplina;Conceito 1;Conceito 2;...
História dos Computadores e da Computação;ICHCC01 – Histórico;ICHCC02 – Gerações de Computadores;...
```

Utilizado pelo script `comparar_sequencias.py` (ACS) para distribuir os materiais de cada aluno por disciplina ao calcular o fitness do algoritmo de referência.

---

### `sequencia_materiais-MARCELO.csv`

Sequências de materiais recebidas pelos alunos no experimento do Marcelo (sequenciamento fixo, sem adaptação), exportadas do banco TCC.

```
id_aluno;disciplina;sequencia_materiais
2;ICFA;1 4 30 32
2;ICFBDES;13 20 28 39 49
...
```

Usado em `comparar_sequencias.py` como base de comparação (`fitness_marcelo`) contra o sequenciamento adaptativo gerado pelo GA.

---

### `Index_of_Learning_Styles.pdf`

Formulário original do **Index of Learning Styles (ILS)** de Felder-Silverman com as 44 questões. Serviu de referência para a criação do questionário ILS no Moodle via `criar_questionario_ils.php`.
