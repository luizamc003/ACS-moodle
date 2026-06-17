"""
Calcula o fitness das sequências do experimento anterior.

Uso:
  python avaliar_experimento.py
  python avaliar_experimento.py --csv results/fitness_experimento.csv
"""

import argparse
import csv
import numpy as np

from acs.instance import Instance
from acs.objective import fitness
from utils.timer import Timer

BASE = "instances/luiza"

# Mapeamento: código do tema no CSV -> instance.txt da pasta LOM
TOPIC_INSTANCE = {
    "ICFA":    f"{BASE}/Algorithm/instance.txt",
    "ICHCC":   f"{BASE}/History of Computation And Computer History/instance.txt",
    "ICLSN":   f"{BASE}/Numeric System And Logic/instance.txt",
    "ICFBDES": f"{BASE}/Database And Software Engineering/instance.txt",
    "ICFSOOC": f"{BASE}/Operation System And Computer Organization/instance.txt",
    "ICRC":    f"{BASE}/Computer Network/instance.txt",
}


def carregar_sequencias(csv_file):
    """Lê sequencia_materiais_experimento.csv e retorna dict {aluno: {tema: [ids]}}."""
    sequencias = {}
    with open(csv_file, encoding="utf-8") as f:
        reader = csv.reader(f, delimiter=";")
        next(reader)  # pula cabeçalho
        for row in reader:
            if not row:
                continue
            aluno_id, tema, ids_str = row[0], row[1], row[2]
            ids = [int(x) for x in ids_str.strip().split()]
            sequencias.setdefault(aluno_id, {})[tema] = ids
    return sequencias


def sequencia_para_mascara(ids_materiais, instance):
    """Converte lista de IDs para vetor booleano na ordem do instance."""
    mascara = np.zeros(instance.num_materials, dtype=bool)
    id_para_idx = {mid: i for i, mid in enumerate(instance.materials_keys)}
    for mid in ids_materiais:
        if mid in id_para_idx:
            mascara[id_para_idx[mid]] = True
        else:
            print(f"  AVISO: material {mid} não encontrado na instância")
    return mascara


def avaliar(sequencias, verbose=False):
    resultados = []
    instancias = {}

    for aluno_id, temas in sorted(sequencias.items(), key=lambda x: int(x[0])):
        for tema, ids_materiais in sorted(temas.items()):
            if tema not in TOPIC_INSTANCE:
                print(f"AVISO: tema '{tema}' não mapeado, pulando.")
                continue

            # Carrega instância (cache para não recarregar a cada aluno)
            instance_file = TOPIC_INSTANCE[tema]
            if instance_file not in instancias:
                instancias[instance_file] = Instance.load_from_file(instance_file)
            instance = instancias[instance_file]

            # Encontra índice do aluno na instância
            if aluno_id not in instance.learners_keys:
                print(f"  AVISO: aluno {aluno_id} não encontrado na instância de {tema}")
                continue
            aluno_idx = instance.learners_keys.index(aluno_id)

            mascara = sequencia_para_mascara(ids_materiais, instance)
            timer = Timer()

            # Calcula fitness com detalhes por componente
            data = []
            total = fitness(mascara, instance, aluno_idx, timer, data=data)
            cobertura, dificuldade, tempo, balanceamento, estilo = data[0]

            if verbose:
                print(f"Aluno {aluno_id} | {tema} | materiais={ids_materiais}")
                print(f"  cobertura={cobertura:.3f} dificuldade={dificuldade:.3f} "
                      f"tempo={tempo:.3f} balanceamento={balanceamento:.3f} estilo={estilo:.3f}")
                print(f"  TOTAL FITNESS = {total:.4f}")

            resultados.append({
                "id_aluno": aluno_id,
                "tema": tema,
                "num_materiais": int(mascara.sum()),
                "fitness_total": total,
                "cobertura": cobertura,
                "dificuldade": dificuldade,
                "tempo": tempo,
                "balanceamento": balanceamento,
                "estilo": estilo,
                "sequencia": " ".join(str(x) for x in ids_materiais),
            })

    return resultados


def exportar_csv(resultados, output_file):
    campos = ["id_aluno", "tema", "num_materiais", "fitness_total",
              "cobertura", "dificuldade", "tempo", "balanceamento", "estilo", "sequencia"]
    with open(output_file, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=campos, delimiter=";")
        writer.writeheader()
        for r in resultados:
            writer.writerow({k: f"{v:.4f}" if isinstance(v, float) else v for k, v in r.items()})
    print(f"\nExportado: {output_file} ({len(resultados)} linhas)")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--entrada", default="instances/luiza/sequencia_materiais_experimento.csv")
    parser.add_argument("--csv", default=None, help="Salva resultados em CSV")
    parser.add_argument("-v", "--verbose", action="store_true")
    args = parser.parse_args()

    print(f"Lendo sequências de: {args.entrada}")
    sequencias = carregar_sequencias(args.entrada)
    print(f"  {len(sequencias)} alunos, {sum(len(t) for t in sequencias.values())} sequências\n")

    resultados = avaliar(sequencias, verbose=args.verbose)

    # Resumo por tema
    from collections import defaultdict
    por_tema = defaultdict(list)
    for r in resultados:
        por_tema[r["tema"]].append(r["fitness_total"])

    print("\n=== Resumo por tema ===")
    for tema, valores in sorted(por_tema.items()):
        print(f"  {tema}: média={np.mean(valores):.4f}  min={np.min(valores):.4f}  max={np.max(valores):.4f}  n={len(valores)}")

    if args.csv:
        exportar_csv(resultados, args.csv)
