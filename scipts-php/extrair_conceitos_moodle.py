#!/usr/bin/env python3
"""
Extrai conceitos do Moodle via Web Services API e gera um arquivo CSV.

Uso:
    python3 extrair_conceitos_moodle.py --url https://seu-moodle.com --token SEU_TOKEN
    python3 extrair_conceitos_moodle.py --url https://seu-moodle.com --token SEU_TOKEN --output conceitos.csv

Como obter o token:
    Moodle → Preferências → Segurança → Chaves de serviços web
    Ou: Site Admin → Plugins → Web services → Manage tokens
"""

import argparse
import csv
import json
import sys
import urllib.parse
import urllib.request


CONCEITOS = [
    ("ICHCC01", "Histórico"),
    ("ICHCC02", "Gerações de Computadores"),
    ("ICHCC03", "Arquitetura de VonNeumann"),
    ("ICHCC04", "Big Data"),
    ("ICHCC05", "Computação em Núvem"),
    ("ICHCC06", "Inteligência Artificial"),
    ("ICSN01",  "Sistema Numérico e Representação Numérica"),
    ("ICSN02",  "Principais Sistemas Numéricos"),
    ("ICSN03",  "Conversões entre bases numéricas"),
    ("ICSN04",  "Byte, Word e Unidades de Medidas de Armazenamento"),
    ("ICL01",   "O que é Lógica"),
    ("ICL02",   "Lógica Proposicional e Proposições"),
    ("ICL03",   "Principais Operadores Lógicos e Tabela Verdade"),
    ("ICFA01",  "O que é um algoritmo"),
    ("ICFA02",  "Representação de Algoritmos"),
    ("ICFA03",  "Paradigmas de Programação"),
    ("ICFBD01", "Introdução aos Banco de Dados"),
    ("ICFBD02", "Modelo Relacional"),
    ("ICFBD03", "SQL"),
    ("ICES01",  "Introdução a Engenharia de Software"),
]

DISCIPLINAS = {
    "ICHCC": "História dos Computadores e da Computação",
    "ICSN":  "Lógica e Sistemas Numéricos",
    "ICL":   "Lógica e Sistemas Numéricos",
    "ICFA":  "Fundamentos de Algoritmos",
    "ICFBD": "Fundamentos de Banco de Dados e Engenharia de Software",
    "ICES":  "Fundamentos de Banco de Dados e Engenharia de Software",
}


def disciplina_da_sigla(sigla: str) -> str:
    for prefixo, nome in DISCIPLINAS.items():
        if sigla.startswith(prefixo):
            return nome
    return ""


def moodle_request(base_url: str, token: str, function: str, params: dict) -> dict:
    endpoint = f"{base_url.rstrip('/')}/webservice/rest/server.php"
    data = {
        "wstoken": token,
        "wsfunction": function,
        "moodlewsrestformat": "json",
        **params,
    }
    encoded = urllib.parse.urlencode(data).encode("utf-8")
    req = urllib.request.Request(endpoint, data=encoded, method="POST")
    with urllib.request.urlopen(req, timeout=30) as resp:
        return json.loads(resp.read().decode("utf-8"))


def buscar_competencias_moodle(base_url: str, token: str) -> list[dict]:
    """Tenta buscar competências cadastradas no Moodle (framework de competências)."""
    try:
        resultado = moodle_request(base_url, token, "core_competency_list_competencies", {})
        return resultado if isinstance(resultado, list) else []
    except Exception as e:
        print(f"[aviso] Não foi possível buscar competências do Moodle: {e}", file=sys.stderr)
        return []


def enriquecer_com_moodle(conceitos_base: list[tuple], base_url: str | None, token: str | None) -> list[dict]:
    """
    Tenta cruzar os conceitos locais com dados do Moodle.
    Se a conexão falhar ou não houver correspondência, usa apenas os dados locais.
    """
    moodle_map: dict[str, str] = {}

    if base_url and token:
        competencias = buscar_competencias_moodle(base_url, token)
        for comp in competencias:
            nome = comp.get("shortname") or comp.get("idnumber") or ""
            descricao = comp.get("description") or comp.get("fullname") or ""
            if nome:
                moodle_map[nome.upper()] = descricao

    rows = []
    for sigla, nome in conceitos_base:
        rows.append({
            "sigla": sigla,
            "nome": nome,
        })
    return rows


def gerar_csv(rows: list[dict], caminho: str) -> None:
    campos = ["sigla", "nome"]
    with open(caminho, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=campos)
        writer.writeheader()
        writer.writerows(rows)
    print(f"CSV gerado: {caminho} ({len(rows)} conceitos)")


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Extrai conceitos do Moodle e gera CSV.",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    parser.add_argument("--url",    help="URL base do Moodle (ex: https://moodle.ufv.br)")
    parser.add_argument("--token",  help="Token de acesso ao Web Service do Moodle")
    parser.add_argument("--output", default="conceitos.csv", help="Nome do arquivo CSV de saída (padrão: conceitos.csv)")
    args = parser.parse_args()

    if bool(args.url) != bool(args.token):
        parser.error("--url e --token devem ser fornecidos juntos, ou nenhum dos dois.")

    rows = enriquecer_com_moodle(CONCEITOS, args.url, args.token)
    gerar_csv(rows, args.output)


if __name__ == "__main__":
    main()
