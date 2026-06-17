#!/usr/bin/env python3
"""
Extrai dados dos alunos do dump SQL e gera learners.csv no formato:

id;tempo_min;tempo_max;ativo_reflexivo;sensorial_intuitivo;visual_verbal;sequencial_global;ICHCC01;ICHCC02;...

Os objetivos de aprendizagem (colunas de conceito) são 1 para todos os alunos (fixo para a turma).

Uso:
    python3 extrair_learners.py
    python3 extrair_learners.py --sql dados_tcc.sql --output learners.csv
"""

import argparse
import csv
import re


# Conceitos do curso (ordem fixa)
CONCEITOS = [
    "ICHCC01", "ICHCC02", "ICHCC03", "ICHCC04", "ICHCC05", "ICHCC06",
    "ICSN01", "ICSN02", "ICSN03", "ICSN04",
    "ICL01", "ICL02", "ICL03",
    "ICFA01", "ICFA02", "ICFA03",
    "ICFBD01", "ICFBD02", "ICFBD03",
    "ICES01",
]


def parse_alunos(sql_path: str) -> dict[int, dict]:
    """Retorna mapa id → {tempo_min, tempo_max}."""
    alunos: dict[int, dict] = {}
    with open(sql_path, encoding="utf-8") as f:
        content = f.read()

    # Localiza o INSERT da tabela aluno (não mld_aluno)
    match = re.search(r"INSERT INTO `aluno` VALUES (.+?);", content, re.DOTALL)
    if not match:
        return alunos

    for row in re.findall(r"\(([^)]+)\)", match.group(1)):
        parts = [p.strip().strip("'") for p in row.split(",")]
        # id, nome, matricula, curso, tempo_de_curso, telefone, experiencia,
        # email, habilidade, descricao_experiencia, tempo_min, tempo_max, identificador_grupo
        if len(parts) >= 13:
            try:
                alunos[int(parts[0])] = {
                    "tempo_min": int(parts[10]),
                    "tempo_max": int(parts[11]),
                }
            except (ValueError, IndexError):
                pass
    return alunos


def parse_estilos(sql_path: str) -> dict[int, dict]:
    """Retorna mapa id_aluno → {atiref, semint, visver, seqglo}."""
    estilos: dict[int, dict] = {}
    with open(sql_path, encoding="utf-8") as f:
        content = f.read()

    match = re.search(r"INSERT INTO `estilo_de_aprendizagem` VALUES (.+?);", content, re.DOTALL)
    if not match:
        return estilos

    for row in re.findall(r"\(([^)]+)\)", match.group(1)):
        parts = [p.strip().strip("'") for p in row.split(",")]
        # id, id_aluno, atiref, semint, visver, seqglo
        if len(parts) >= 6:
            try:
                estilos[int(parts[1])] = {
                    "atiref": int(parts[2]),
                    "semint": int(parts[3]),
                    "visver": int(parts[4]),
                    "seqglo": int(parts[5]),
                }
            except (ValueError, IndexError):
                pass
    return estilos


def gerar_csv(sql_path: str, output_path: str) -> None:
    alunos = parse_alunos(sql_path)
    estilos = parse_estilos(sql_path)

    # Apenas alunos que têm estilo de aprendizagem registrado
    ids = sorted(set(alunos) & set(estilos))

    campos = ["id", "tempo_min", "tempo_max",
              "ativo_reflexivo", "sensorial_intuitivo", "visual_verbal", "sequencial_global",
              *CONCEITOS]

    with open(output_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=campos, delimiter=";")
        writer.writeheader()
        for id_aluno in ids:
            a = alunos[id_aluno]
            e = estilos[id_aluno]
            row = {
                "id":                   id_aluno,
                "tempo_min":            a["tempo_min"],
                "tempo_max":            a["tempo_max"],
                "ativo_reflexivo":      e["atiref"],
                "sensorial_intuitivo":  e["semint"],
                "visual_verbal":        e["visver"],
                "sequencial_global":    e["seqglo"],
            }
            # Objetivos de aprendizagem: 1 para todos os conceitos (fixo para a turma)
            for c in CONCEITOS:
                row[c] = 1
            writer.writerow(row)

    print(f"CSV gerado: {output_path} ({len(ids)} alunos)")


def main() -> None:
    parser = argparse.ArgumentParser(description="Extrai dados dos learners do dump SQL.")
    parser.add_argument("--sql",    default="dados_tcc.sql", help="Caminho do dump SQL")
    parser.add_argument("--output", default="learners.csv",  help="Arquivo CSV de saída")
    args = parser.parse_args()
    gerar_csv(args.sql, args.output)


if __name__ == "__main__":
    main()
