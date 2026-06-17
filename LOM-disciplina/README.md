# LOM-disciplina — Metadados LOM dos Materiais

Arquivos XML com metadados no padrão **IEEE LOM (Learning Object Metadata)** para cada material didático do curso de Introdução à Computação utilizado no experimento.

Cada subpasta representa uma disciplina. Dentro dela, os arquivos são numerados sequencialmente (`0.xml`, `1.xml`, ...) correspondendo ao `material_id` usado no banco de dados e nas instâncias ACS.

---

## Estrutura

```
LOM-disciplina/
├── Algorithm/                                    (39 materiais: 0–38)
├── Computer Network/                             (38 materiais: 0–37)
├── Database And Software Engineering/            (58 materiais: 0–57)
├── History of Computation And Computer History/  (62 materiais: 0–61)
├── Numeric System And Logic/                     (56 materiais: 0–55)
└── Operation System And Computer Organization/   (31 materiais: 0–30)
```

---

## Disciplinas e conceitos cobertos

Existem csvs para cada pasta associando cada material com os conceitos abordados.

| Pasta                                       | Sigla ACS | Conceitos                      |
| ------------------------------------------- | --------- | ------------------------------ |
| Algorithm                                   | ICFA      | ICFA01–ICFA03                  |
| Computer Network                            | ICRC      | ICRC01–ICRC05                  |
| Database And Software Engineering           | ICFBDES   | ICFBD01–ICFBD03, ICES01–ICES03 |
| History of Computation And Computer History | ICHCC     | ICHCC01–ICHCC06                |
| Numeric System And Logic                    | ICLSN     | ICSN01–ICSN04, ICL01–ICL03     |
| Operation System And Computer Organization  | ICFSOOC   | ICFSOOC01–ICFSOOC03            |

---

## Estrutura de cada XML LOM

Cada arquivo segue o schema `http://ltsc.ieee.org/xsd/LOM` com os campos:

| Seção LOM     | Campo                  | Uso                                                     |
| ------------- | ---------------------- | ------------------------------------------------------- |
| `general`     | `title`                | Título do material (usado como nome do SCORM no Moodle) |
| `general`     | `description`          | Descrição do conteúdo                                   |
| `general`     | `keyword`              | Palavras-chave                                          |
| `technical`   | `format`               | Tipo do arquivo (ex: `application/pdf`)                 |
| `educational` | `difficulty`           | Nível de dificuldade (`very easy` → `very difficult`)   |
| `educational` | `typicalLearningTime`  | Duração estimada em ISO 8601 (ex: `PT28M18S`)           |
| `educational` | `learningResourceType` | Tipo do recurso (narrative text, figure, diagram...)    |

---

## Como esses arquivos são usados

1. **`criar_scorms_lom.php`** — lê cada XML e cria um SCORM no Moodle com o `imsmanifest.xml` gerado a partir dos metadados.
2. **`associar_scorm_tags_dificuldade.php`** — extrai o campo `<difficulty>` e associa a tag correspondente ao SCORM.
3. **`popular_tempo_estimado.php`** — extrai `<typicalLearningTime>`, converte para minutos e salva na tabela `mdl_local_scorm_lom`.
4. **Instâncias ACS** — os XMLs servem de base para montar os arquivos `instance.txt` usados pelo algoritmo de sequenciamento.
