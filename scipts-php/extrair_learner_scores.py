#!/usr/bin/env python3
"""
Extrai pontuação dos alunos por conceito a partir do dump SQL e gera um CSV.

Formato de saída: id_aluno;sigla_conceito;habilidade

Uso:
    python3 extrair_learner_scores.py
    python3 extrair_learner_scores.py --sql dados_tcc.sql --output learner_scores.csv
"""

import argparse
import csv
import re


def parse_insert_values(line: str) -> list[tuple]:
    """Extrai tuplas de uma linha INSERT INTO ... VALUES (...)."""
    match = re.search(r"VALUES\s*(.+);?\s*$", line, re.IGNORECASE | re.DOTALL)
    if not match:
        return []
    raw = match.group(1).strip().rstrip(";")
    return re.findall(r"\(([^)]+)\)", raw)


def parse_conceitos(sql_path: str) -> dict[int, str]:
    """Retorna mapa id_conceito → sigla."""
    conceitos: dict[int, str] = {}
    in_block = False
    buffer = []

    with open(sql_path, encoding="utf-8") as f:
        for line in f:
            if "INSERT INTO `conceito`" in line or (in_block and line.strip()):
                in_block = True
                buffer.append(line)
            if in_block and "UNLOCK TABLES" in line:
                break

    text = " ".join(buffer)
    for row in re.findall(r"\(([^)]+)\)", text):
        parts = [p.strip().strip("'") for p in row.split(",")]
        if len(parts) >= 3:
            try:
                conceitos[int(parts[0])] = parts[1]
            except ValueError:
                pass

    return conceitos


def parse_aluno_conceito(sql_path: str) -> list[tuple[int, int, float]]:
    """Retorna lista de (id_aluno, id_conceito, habilidade)."""
    rows: list[tuple[int, int, float]] = []
    in_block = False
    buffer = []

    with open(sql_path, encoding="utf-8") as f:
        for line in f:
            if "INSERT INTO `aluno_conceito`" in line:
                in_block = True
                buffer.append(line)
                continue
            if in_block:
                buffer.append(line)
            if in_block and "UNLOCK TABLES" in line:
                break

    text = " ".join(buffer)
    for row in re.findall(r"\((\d+),(\d+),(\d+),([0-9.]+)\)", text):
        _, id_aluno, id_conceito, habilidade = row
        rows.append((int(id_aluno), int(id_conceito), float(habilidade)))

    return rows


def gerar_csv(sql_path: str, output_path: str) -> None:
    conceitos = parse_conceitos(sql_path)
    registros = parse_aluno_conceito(sql_path)

    with open(output_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f, delimiter=";")
        writer.writerow(["id_aluno", "conceito", "habilidade"])
        for id_aluno, id_conceito, habilidade in registros:
            sigla = conceitos.get(id_conceito, f"ID{id_conceito}")
            writer.writerow([id_aluno, sigla, habilidade])

    print(f"CSV gerado: {output_path} ({len(registros)} registros)")


def main() -> None:
    parser = argparse.ArgumentParser(description="Extrai learner scores do dump SQL.")
    parser.add_argument("--sql",    default="dados_tcc.sql",      help="Caminho do dump SQL")
    parser.add_argument("--output", default="learner_scores.csv", help="Arquivo CSV de saída")
    args = parser.parse_args()
    gerar_csv(args.sql, args.output)


if __name__ == "__main__":
    main()
