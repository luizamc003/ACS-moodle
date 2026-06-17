"""
Compara dois sequenciamentos para cada aluno por disciplina:

  1. experimento_luiza: roda o algoritmo genético 5 vezes para cada disciplina
     e salva as sequências geradas em results/experimento_luiza/<disciplina>/rodada_N.csv
     O fitness de cada rodada é calculado e a média é usada no comparativo.

  2. fitness_marcelo: lê results/sequencias_algorithm.csv, distribui os materiais
     por disciplina conforme conceitos_por_disciplina.csv, e calcula o fitness.

  Saída:
    results/experimento_luiza/<disciplina>/rodada_N.csv  — sequências geradas
    results/comparacao.csv                               — comparativo final

Uso:
  python comparar_sequencias.py
  python comparar_sequencias.py --n 5 --csv results/comparacao.csv
"""

import argparse
import csv
import os
import random

import numpy as np

from acs.instance import Instance
from acs.objective import fitness
from algorithms.ga.main import genetic_algorithm
from algorithms.ga.config import Config as GAConfig
from utils.timer import Timer

BASE = "instances/luiza"

# Mapeamento sigla -> pasta da instância
DISCIPLINAS = {
    "ICHCC":   f"{BASE}/History of Computation And Computer History",
    "ICLSN":   f"{BASE}/Numeric System And Logic",
    "ICFA":    f"{BASE}/Algorithm",
    "ICFBDES": f"{BASE}/Database And Software Engineering",
    "ICFSOOC": f"{BASE}/Operation System And Computer Organization",
    "ICRC":    f"{BASE}/Computer Network",
}

# Conceitos por disciplina (sigla -> lista de prefixos de conceitos)
CONCEITOS_DISCIPLINA = {
    "ICHCC":   ["ICHCC"],
    "ICLSN":   ["ICSN", "ICL"],
    "ICFA":    ["ICFA"],
    "ICFBDES": ["ICFBD", "ICES"],
    "ICFSOOC": ["ICFSOOC"],
    "ICRC":    ["ICRC"],
}

SEQUENCIAS_ALGORITHM = "results/sequencias_algorithm.csv"
OUTPUT_BASE = "results/experimento_luiza"


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def carregar_instancia(sigla):
    pasta = DISCIPLINAS[sigla]
    instance_file = os.path.join(pasta, "instance.txt")
    return Instance.load_from_file(instance_file)


def calcular_fitness_detalhado(mascara, instance, aluno_idx):
    timer = Timer()
    data = []
    total = fitness(mascara, instance, aluno_idx, timer, data=data)
    cobertura, dificuldade, tempo, balanceamento, estilo = data[0]
    return {
        "fitness_total": total,
        "cobertura": cobertura,
        "dificuldade": dificuldade,
        "tempo": tempo,
        "balanceamento": balanceamento,
        "estilo": estilo,
    }


def mascara_para_ids(mascara, materials_keys):
    return [materials_keys[i] for i, v in enumerate(mascara) if v]


def salvar_rodada_csv(filepath, instance, selected_materials):
    """Salva CSV com id_aluno, num_materiais, fitness e sequência para uma rodada."""
    os.makedirs(os.path.dirname(filepath), exist_ok=True)
    campos = ["id_aluno", "num_materiais", "fitness_total",
              "cobertura", "dificuldade", "tempo", "balanceamento", "estilo", "sequencia"]
    with open(filepath, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=campos, delimiter=";")
        writer.writeheader()
        for aluno_idx, aluno_id in enumerate(instance.learners_keys):
            mascara = selected_materials[aluno_idx]
            ids = mascara_para_ids(mascara, instance.materials_keys)
            comp = calcular_fitness_detalhado(mascara, instance, aluno_idx)
            writer.writerow({
                "id_aluno": aluno_id,
                "num_materiais": int(mascara.sum()),
                "fitness_total": f"{comp['fitness_total']:.4f}",
                "cobertura": f"{comp['cobertura']:.4f}",
                "dificuldade": f"{comp['dificuldade']:.4f}",
                "tempo": f"{comp['tempo']:.4f}",
                "balanceamento": f"{comp['balanceamento']:.4f}",
                "estilo": f"{comp['estilo']:.4f}",
                "sequencia": " ".join(str(x) for x in ids),
            })


# ---------------------------------------------------------------------------
# Parte 1: experimento_luiza — roda GA 5 vezes por disciplina
# ---------------------------------------------------------------------------

def rodar_experimento_luiza(n=5, stagnation=100):
    """
    Roda o GA n vezes para cada disciplina, salva os CSVs e retorna
    {sigla: {aluno_id: {componente: média_de_n_rodadas}}}
    """
    config = GAConfig.load_test()
    config.max_stagnation = stagnation

    resultados = {}  # sigla -> aluno_id -> {comp: [valores]}

    for sigla in DISCIPLINAS:
        pasta_instance = DISCIPLINAS[sigla]
        instance_file = os.path.join(pasta_instance, "instance.txt")

        if not os.path.exists(instance_file):
            print(f"  AVISO: instância não encontrada para {sigla} ({instance_file}), pulando.")
            continue

        print(f"\n  Disciplina: {sigla}")
        instance = Instance.load_from_file(instance_file)
        resultados[sigla] = {aid: {} for aid in instance.learners_keys}

        for run in range(1, n + 1):
            seed = run * 42
            np.random.seed(seed)
            random.seed(seed)

            out_info = {}
            print(f"    Rodada {run}/{n} (seed={seed})...")

            ga_results = genetic_algorithm(instance, config, fitness, out_info=out_info)

            # ga_results é lista de (mascara, fitness) por aluno
            selected = np.array([r[0] for r in ga_results])

            # Salva CSV da rodada
            nome_disciplina = sigla.replace("/", "_")
            csv_path = os.path.join(OUTPUT_BASE, nome_disciplina, f"rodada_{run}.csv")
            salvar_rodada_csv(csv_path, instance, selected)
            print(f"    Salvo: {csv_path}")

            # Acumula resultados por aluno
            for aluno_idx, aluno_id in enumerate(instance.learners_keys):
                mascara = selected[aluno_idx]
                comp = calcular_fitness_detalhado(mascara, instance, aluno_idx)
                for k, v in comp.items():
                    resultados[sigla][aluno_id].setdefault(k, []).append(v)

    # Calcula médias
    medias = {}
    for sigla, alunos in resultados.items():
        medias[sigla] = {}
        for aluno_id, comps in alunos.items():
            medias[sigla][aluno_id] = {k: np.mean(vs) for k, vs in comps.items()}

    return medias


# ---------------------------------------------------------------------------
# Parte 2: fitness_marcelo — distribui sequencia_algorithm por disciplina
# ---------------------------------------------------------------------------

def carregar_sequencias_algorithm():
    """Retorna {aluno_id: [ids_materiais]}"""
    sequencias = {}
    with open(SEQUENCIAS_ALGORITHM, encoding="utf-8") as f:
        reader = csv.DictReader(f, delimiter=";")
        for row in reader:
            aluno_id = row["id_aluno"]
            ids = [int(x) for x in row["sequencia_materiais"].strip().split()]
            sequencias[aluno_id] = ids
    return sequencias


def material_pertence_disciplina(material_id, instance, sigla):
    """Verifica se um material cobre pelo menos um conceito da disciplina."""
    prefixos = CONCEITOS_DISCIPLINA[sigla]
    if material_id not in instance.materials_keys:
        return False
    mat_idx = instance.materials_keys.index(material_id)
    concepts_keys = instance.concepts_keys

    for conceito_idx, coberto in enumerate(instance.concepts_materials[:, mat_idx]):
        if coberto and any(concepts_keys[conceito_idx].startswith(p) for p in prefixos):
            return True
    return False


def rodar_fitness_marcelo():
    """
    Para cada disciplina, filtra os materiais do sequencias_algorithm que pertencem
    a ela e calcula o fitness.
    Retorna {sigla: {aluno_id: {componente: valor, 'sequencia': str}}}
    """
    sequencias_alg = carregar_sequencias_algorithm()
    resultados = {}

    for sigla in DISCIPLINAS:
        pasta_instance = DISCIPLINAS[sigla]
        instance_file = os.path.join(pasta_instance, "instance.txt")

        if not os.path.exists(instance_file):
            print(f"  AVISO: instância não encontrada para {sigla}, pulando.")
            continue

        instance = Instance.load_from_file(instance_file)
        resultados[sigla] = {}

        for aluno_id, todos_ids in sequencias_alg.items():
            if aluno_id not in instance.learners_keys:
                continue

            # Filtra materiais que existem nesta instância
            ids_disciplina = [mid for mid in todos_ids if mid in instance.materials_keys]

            mascara = np.zeros(instance.num_materials, dtype=bool)
            id_para_idx = {mid: i for i, mid in enumerate(instance.materials_keys)}
            for mid in ids_disciplina:
                mascara[id_para_idx[mid]] = True

            aluno_idx = instance.learners_keys.index(aluno_id)
            comp = calcular_fitness_detalhado(mascara, instance, aluno_idx)
            comp["sequencia"] = " ".join(str(x) for x in ids_disciplina)
            resultados[sigla][aluno_id] = comp

    return resultados


# ---------------------------------------------------------------------------
# Comparativo
# ---------------------------------------------------------------------------

def gerar_comparativo(medias_exp, fitness_marcelo):
    """
    Retorna lista de linhas para o CSV comparativo, ordenadas por id_aluno crescente.
    Para cada aluno e disciplina, mostra lado a lado os dois resultados.
    """
    todos_alunos = sorted(
        set(
            aid for sig in medias_exp.values() for aid in sig
        ) | set(
            aid for sig in fitness_marcelo.values() for aid in sig
        ),
        key=lambda x: int(x)
    )

    linhas = []
    for aluno_id in todos_alunos:
        for sigla in sorted(DISCIPLINAS.keys()):
            exp = medias_exp.get(sigla, {}).get(aluno_id)
            marc = fitness_marcelo.get(sigla, {}).get(aluno_id)

            if exp is None and marc is None:
                continue

            ft_exp = exp["fitness_total"] if exp else None
            ft_marc = marc["fitness_total"] if marc else None

            if ft_exp is not None and ft_marc is not None:
                diff = ft_exp - ft_marc
                vencedor = "experimento_luiza" if diff < 0 else ("fitness_marcelo" if diff > 0 else "empate")
            else:
                diff = None
                vencedor = "-"

            linhas.append({
                "id_aluno": aluno_id,
                "disciplina": sigla,
                # experimento_luiza (média de 5 rodadas)
                "exp_fitness_media": f"{ft_exp:.4f}" if ft_exp is not None else "-",
                "exp_cobertura": f"{exp['cobertura']:.4f}" if exp else "-",
                "exp_dificuldade": f"{exp['dificuldade']:.4f}" if exp else "-",
                "exp_tempo": f"{exp['tempo']:.4f}" if exp else "-",
                "exp_balanceamento": f"{exp['balanceamento']:.4f}" if exp else "-",
                "exp_estilo": f"{exp['estilo']:.4f}" if exp else "-",
                # fitness_marcelo
                "marc_fitness": f"{ft_marc:.4f}" if ft_marc is not None else "-",
                "marc_cobertura": f"{marc['cobertura']:.4f}" if marc else "-",
                "marc_dificuldade": f"{marc['dificuldade']:.4f}" if marc else "-",
                "marc_tempo": f"{marc['tempo']:.4f}" if marc else "-",
                "marc_balanceamento": f"{marc['balanceamento']:.4f}" if marc else "-",
                "marc_estilo": f"{marc['estilo']:.4f}" if marc else "-",
                "marc_sequencia": marc["sequencia"] if marc else "-",
                # diferença (exp - marcelo): negativo = exp melhor
                "diferenca_fitness": f"{diff:+.4f}" if diff is not None else "-",
                "melhor": vencedor,
            })

    return linhas


def imprimir_resumo(linhas):
    print("\n" + "=" * 130)
    print(f"{'ID':>6}  {'Disciplina':<10}  "
          f"{'Exp.Fitness':>12}  {'Marc.Fitness':>12}  {'Diferença':>10}  {'Melhor':<20}")
    print("-" * 130)

    aluno_atual = None
    for l in linhas:
        if l["id_aluno"] != aluno_atual:
            if aluno_atual is not None:
                print()
            aluno_atual = l["id_aluno"]
        print(f"{l['id_aluno']:>6}  {l['disciplina']:<10}  "
              f"{l['exp_fitness_media']:>12}  {l['marc_fitness']:>12}  "
              f"{l['diferenca_fitness']:>10}  {l['melhor']:<20}")

    print("=" * 130)

    # Resumo geral
    diffs = [float(l["diferenca_fitness"]) for l in linhas if l["diferenca_fitness"] not in ("-", "")]
    if diffs:
        ganhos_exp = sum(1 for d in diffs if d < 0)
        ganhos_marc = sum(1 for d in diffs if d > 0)
        empates = sum(1 for d in diffs if d == 0)
        print(f"\nResumo: experimento_luiza melhor em {ganhos_exp} casos | "
              f"fitness_marcelo melhor em {ganhos_marc} casos | "
              f"empates: {empates} | média da diferença: {np.mean(diffs):+.4f}")


def exportar_csv(linhas, output_file):
    campos = [
        "id_aluno", "disciplina",
        "exp_fitness_media", "exp_cobertura", "exp_dificuldade",
        "exp_tempo", "exp_balanceamento", "exp_estilo",
        "marc_fitness", "marc_cobertura", "marc_dificuldade",
        "marc_tempo", "marc_balanceamento", "marc_estilo",
        "marc_sequencia", "diferenca_fitness", "melhor",
    ]
    os.makedirs(os.path.dirname(output_file), exist_ok=True)
    with open(output_file, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=campos, delimiter=";")
        writer.writeheader()
        writer.writerows(linhas)
    print(f"\nExportado: {output_file} ({len(linhas)} linhas)")


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--n", type=int, default=5, help="Número de rodadas GA por disciplina (padrão: 5)")
    parser.add_argument("--csv", default="results/comparacao.csv", help="Arquivo de saída do comparativo")
    parser.add_argument("--stagnation", type=int, default=100,
                        help="Critério de parada do GA: iterações sem melhora (padrão: 100)")
    args = parser.parse_args()

    print(f"=== Parte 1: rodando experimento_luiza ({args.n} vezes por disciplina, stagnation={args.stagnation}) ===")
    medias_exp = rodar_experimento_luiza(n=args.n, stagnation=args.stagnation)

    print(f"\n=== Parte 2: calculando fitness_marcelo por disciplina ===")
    fm = rodar_fitness_marcelo()

    print(f"\n=== Gerando comparativo ===")
    linhas = gerar_comparativo(medias_exp, fm)

    imprimir_resumo(linhas)
    exportar_csv(linhas, args.csv)
