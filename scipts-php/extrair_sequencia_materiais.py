#!/usr/bin/env python3
"""
Extrai a sequência de materiais por aluno e disciplina para alunos com identificador_grupo = 1
(alunos que receberam o sequenciamento adaptado).

Formato de saída: id_aluno;disciplina;sequencia_materiais

Uso:
    python3 extrair_sequencia_materiais.py
    python3 extrair_sequencia_materiais.py --sql dados_tcc.sql --output sequencia_materiais.csv
"""

import argparse
import csv
import re


def parse_alunos_grupo1(sql_path: str) -> set[int]:
    """Retorna IDs dos alunos com identificador_grupo = 1."""
    with open(sql_path, encoding="utf-8") as f:
        content = f.read()

    match = re.search(r"INSERT INTO `aluno` VALUES (.+?);", content, re.DOTALL)
    if not match:
        return set()

    ids = set()
    for row in re.findall(r"\(([^)]+)\)", match.group(1)):
        parts = [p.strip().strip("'") for p in row.split(",")]
        # id, nome, matricula, curso, tempo_de_curso, telefone, experiencia,
        # email, habilidade, descricao_experiencia, tempo_min, tempo_max, identificador_grupo
        if len(parts) >= 13:
            try:
                if int(parts[12]) == 1:
                    ids.add(int(parts[0]))
            except (ValueError, IndexError):
                pass
    return ids


def parse_disciplinas(sql_path: str) -> dict[int, str]:
    """Retorna mapa id → sigla da disciplina."""
    with open(sql_path, encoding="utf-8") as f:
        content = f.read()

    match = re.search(r"INSERT INTO `disciplina` VALUES (.+?);", content, re.DOTALL)
    if not match:
        return {}

    disciplinas: dict[int, str] = {}
    for row in re.findall(r"\(([^)]+)\)", match.group(1)):
        parts = [p.strip().strip("'") for p in row.split(",")]
        if len(parts) >= 2:
            try:
                disciplinas[int(parts[0])] = parts[1]
            except (ValueError, IndexError):
                pass
    return disciplinas


def parse_materiais(sql_path: str) -> list[tuple[int, int, str]]:
    """Retorna lista de (id_aluno, id_disciplina, sequencia)."""
    with open(sql_path, encoding="utf-8") as f:
        content = f.read()

    match = re.search(r"INSERT INTO `aluno_disciplina_materiais` VALUES (.+?);", content, re.DOTALL)
    if not match:
        return []

    rows = []
    for row in re.findall(r"\((\d+),(\d+),'([^']*)'\)", match.group(0)):
        id_aluno, id_disciplina, sequencia = row
        rows.append((int(id_aluno), int(id_disciplina), sequencia))
    return rows


def gerar_csv(sql_path: str, output_path: str) -> None:
    grupo1 = parse_alunos_grupo1(sql_path)
    disciplinas = parse_disciplinas(sql_path)
    materiais = parse_materiais(sql_path)

    registros = [
        (id_aluno, disciplinas.get(id_disc, f"ID{id_disc}"), seq)
        for id_aluno, id_disc, seq in materiais
        if id_aluno in grupo1
    ]
    registros.sort(key=lambda r: (r[0], r[1]))

    with open(output_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f, delimiter=";")
        writer.writerow(["id_aluno", "disciplina", "sequencia_materiais"])
        writer.writerows(registros)

    alunos_unicos = len({r[0] for r in registros})
    print(f"CSV gerado: {output_path} ({len(registros)} registros, {alunos_unicos} alunos)")


def main() -> None:
    parser = argparse.ArgumentParser(description="Extrai sequência de materiais dos alunos com adaptação.")
    parser.add_argument("--sql",    default="dados_tcc.sql",           help="Caminho do dump SQL")
    parser.add_argument("--output", default="sequencia_materiais.csv", help="Arquivo CSV de saída")
    args = parser.parse_args()
    gerar_csv(args.sql, args.output)


if __name__ == "__main__":
    main()
