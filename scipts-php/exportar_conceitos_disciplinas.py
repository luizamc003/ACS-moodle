"""
Exporta os conceitos agrupados por disciplina para um CSV,
lendo direto do arquivo conceitos_disciplina.sql (sem precisar de banco).

Formato de saída:
  Sigla ; Conceito ; Disciplina1 ; Disciplina2 ; ...

Cada linha é um conceito. A célula marca com "X" a disciplina à qual pertence.

Uso:
  python3 /home/luiza/Desktop/TCC/exportar_conceitos_disciplinas.py
"""

import csv
import re

SQL_PATH = '/home/luiza/Desktop/TCC/conceitos_disciplina.sql'

with open(SQL_PATH, encoding='utf-8') as f:
    content = f.read()

# Parse disciplinas: (id, sigla, nome)
disciplinas = []
match_disc = re.search(r"INSERT INTO `disciplina` VALUES (.+?);", content, re.DOTALL)
if match_disc:
    for m in re.finditer(r"\((\d+),'([^']+)','([^']+)'\)", match_disc.group(1)):
        disciplinas.append({'id': int(m.group(1)), 'sigla': m.group(2), 'nome': m.group(3)})
disciplinas.sort(key=lambda d: d['id'])

# Parse conceitos: (id, sigla, nome, id_disciplina)
conceitos = []
match_conc = re.search(r"INSERT INTO `conceito` VALUES (.+?);", content, re.DOTALL)
if match_conc:
    for m in re.finditer(r"\((\d+),'([^']+)','([^']+)',(\d+)\)", match_conc.group(1)):
        conceitos.append({
            'id': int(m.group(1)),
            'sigla': m.group(2),
            'nome': m.group(3),
            'id_disciplina': int(m.group(4))
        })
conceitos.sort(key=lambda c: (c['id_disciplina'], c['id']))

# Agrupa conceitos por disciplina
from collections import defaultdict
por_disciplina = defaultdict(list)
for c in conceitos:
    por_disciplina[c['id_disciplina']].append(c['sigla'] + ' – ' + c['nome'])

# Descobre o número máximo de conceitos por disciplina
max_conceitos = max(len(v) for v in por_disciplina.values())

# Monta CSV: disciplina → conceito1 | conceito2 | ...
output = '/home/luiza/Desktop/TCC/conceitos_por_disciplina.csv'

with open(output, 'w', newline='', encoding='utf-8') as f:
    cabecalho = ['Disciplina'] + [f'Conceito {i+1}' for i in range(max_conceitos)]
    writer = csv.writer(f, delimiter=';')
    writer.writerow(cabecalho)

    for d in disciplinas:
        concs = por_disciplina[d['id']]
        linha = [d['nome']] + concs + [''] * (max_conceitos - len(concs))
        writer.writerow(linha)

print(f"Exportado: {output}")
print(f"  {len(disciplinas)} disciplinas x até {max_conceitos} conceitos")
