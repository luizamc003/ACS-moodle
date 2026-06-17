import csv
import sys
import numpy as np

from acs.instance import Instance
from acs.objective import fitness
from utils.timer import Timer


INSTANCE_FILE = 'instances/real/instance.txt'
SEQUENCES_FILE = 'sequencias.csv'  # seu arquivo com id_aluno;disciplina;sequencia_materiais


def load_sequences(filepath):
    sequences = []
    with open(filepath, newline='') as f:
        reader = csv.DictReader(f, delimiter=';')
        for row in reader:
            material_ids = [int(x) for x in row['sequencia_materiais'].split()]
            sequences.append({
                'id_aluno': row['id_aluno'],
                'disciplina': row['disciplina'],
                'material_ids': material_ids,
            })
    return sequences


def ids_to_individual(material_ids, num_materials):
    individual = np.zeros(num_materials, dtype=bool)
    for mid in material_ids:
        if mid < num_materials:
            individual[mid] = True
    return individual


def main():
    instance = Instance.load_from_file(INSTANCE_FILE)

    # Mapeia id_aluno (string) para índice na instância
    learner_index = {key: i for i, key in enumerate(instance.learners_keys)}

    sequences = load_sequences(SEQUENCES_FILE)

    print(f"{'aluno':<10} {'disciplina':<12} {'conceitos':<12} {'dificuldade':<14} {'tempo':<10} {'balanceamento':<16} {'estilo':<10} {'total':<10}")
    print("-" * 100)

    for seq in sequences:
        aluno_id = seq['id_aluno']
        if aluno_id not in learner_index:
            print(f"Aluno {aluno_id} não encontrado na instância, pulando.")
            continue

        student = learner_index[aluno_id]
        individual = ids_to_individual(seq['material_ids'], instance.num_materials)
        timer = Timer()

        data = []
        score = fitness(individual, instance, student, timer, data=data)
        conceitos, dificuldade, tempo, balanceamento, estilo = data[0]

        print(f"{aluno_id:<10} {seq['disciplina']:<12} {conceitos:<12.4f} {dificuldade:<14.4f} {tempo:<10.4f} {balanceamento:<16.4f} {estilo:<10.4f} {score:<10.4f}")


if __name__ == '__main__':
    main()
