## Experimento Luiza

Esta seção documenta os scripts e dados adicionados para o experimento de comparação entre sequências geradas pelo algoritmo genético e sequências aplicadas em um experimento real com alunos.

Especifica como rodar o experimento em `evolutionary-ACS-benchmark`, disponível em: https://github.com/lapic-ufjf/evolutionary-ACS-benchmark

### Base de dados (`instances/luiza/`)

A pasta `instances/luiza/` contém os dados do experimento real, organizados por disciplina:

```
instances/luiza/
├── Algorithm/
│   └── instance.txt              # Instância: Fundamentos de Algoritmos
├── Computer Network/
│   └── instance.txt              # Instância: Redes de Computadores
├── Database And Software Engineering/
│   └── instance.txt              # Instância: Banco de Dados e Eng. de Software
├── History of Computation And Computer History/
│   └── instance.txt              # Instância: História da Computação
├── Numeric System And Logic/
│   └── instance.txt              # Instância: Sistemas Numéricos e Lógica
├── Operation System And Computer Organization/
│   └── instance.txt              # Instância: Sistemas Operacionais e Org. de Computadores
├── concepts.csv                  # Conceitos do currículo
├── conceitos_por_disciplina.csv  # Mapeamento conceito → disciplina
├── learners.csv                  # Dados dos alunos
├── learner_scores.csv            # Notas dos alunos
└── sequencia_materiais_experimento.csv  # Sequências aplicadas no experimento real  - dados do Marcelo (referência)
```

Siglas das disciplinas usadas nos scripts:

| Sigla   | Disciplina                                                  |
| ------- | ----------------------------------------------------------- |
| ICFA    | Fundamentos de Algoritmos                                   |
| ICHCC   | História dos Computadores e da Computação                   |
| ICLSN   | Lógica e Sistemas Numéricos                                 |
| ICFBDES | Fundamentos de Banco de Dados e Eng. de Software            |
| ICFSOOC | Fundamentos de Sistemas Operacionais e Org. de Computadores |
| ICRC    | Redes de Computadores                                       |

### Scripts

Todos os scripts abaixo devem ser executados a partir do **diretório raiz** do repositório.

---

#### `comparar_sequencias.py` — Experimento principal

Compara duas abordagens de sequenciamento para cada aluno por disciplina:

1. **experimento_luiza** — roda o algoritmo genético (GA) N vezes por disciplina e calcula a média do fitness obtido.
2. **fitness_marcelo** — lê `results/sequencias_algorithm.csv`, distribui os materiais por disciplina conforme `conceitos_por_disciplina.csv`, e calcula o fitness dessas sequências.

**Saídas geradas:**

- `results/experimento_luiza/<disciplina>/rodada_N.csv` — sequências geradas pelo GA em cada rodada
- `results/comparacao.csv` — comparativo final (fitness médio, componentes e qual abordagem foi melhor)

**Uso:**

```shell
# Execução padrão: 5 rodadas por disciplina, stagnation=100
python3 comparar_sequencias.py

# Customizando parâmetros
python3 comparar_sequencias.py --n 5 --stagnation 100 --csv results/comparacao.csv
```

**Parâmetros:**

- `--n` — número de rodadas do GA por disciplina (padrão: 5)
- `--stagnation` — critério de parada: iterações sem melhora (padrão: 100)
- `--csv` — caminho do arquivo de saída do comparativo (padrão: `results/comparacao.csv`)

**Pré-requisito:** o arquivo `results/sequencias_algorithm.csv` deve existir (gerado pelo `ver_sequencias.py`).

---

#### `avaliar_experimento.py` — Avalia sequências do experimento real

Calcula o fitness das sequências registradas em `sequencia_materiais_experimento.csv` (as sequências aplicadas no experimento real com alunos), usando as instâncias em `instances/luiza/`.

**Uso:**

```shell
python3 avaliar_experimento.py
python3 avaliar_experimento.py --csv results/fitness_experimento.csv
```

**Parâmetros:**

- `--entrada` — arquivo CSV de entrada (padrão: `instances/luiza/sequencia_materiais_experimento.csv`)
- `--csv` — salva os resultados em CSV
- `-v, --verbose` — exibe detalhes de cada sequência avaliada

---

#### `avaliar_sequencias.py` — Avalia sequências de um CSV genérico

Avalia sequências a partir de um arquivo CSV com formato `id_aluno;disciplina;sequencia_materiais`, calculando cada componente do fitness.

**Uso:**

```shell
python3 avaliar_sequencias.py
```

> Edite as variáveis `INSTANCE_FILE` e `SEQUENCES_FILE` no topo do script para apontar para seus arquivos.

### Fluxo completo do experimento

```
instances/luiza/          ← base de dados do experimento real
        │
        ├─ sequencia_materiais_experimento.csv
        │         │
        │         └─ avaliar_experimento.py ──► results/fitness_experimento.csv
        │
results/sequencias_algorithm.csv  (gerado via ver_sequencias.py)
        │
        └─ comparar_sequencias.py  (roda GA 5× por disciplina)
                  │
                  ├─ results/experimento_luiza/<disciplina>/rodada_N.csv
                  └─ results/comparacao.csv  ← comparativo final
```

## Resultados

Em results, tem cada sequenciamento gerado pelo algoritmo por disciplina. O algoritmo foi executado 5 vezes por disciplina.

### Requisitos adicionais

Os scripts do experimento utilizam as mesmas dependências do projeto base (ver seção [Requirements](#requirements)). Certifique-se de que o ambiente virtual está ativo e que as dependências estão instaladas:

```shell
pip3 install -r requirements.txt
```
