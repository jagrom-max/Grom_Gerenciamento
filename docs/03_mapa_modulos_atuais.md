# Mapa Atual do Sistema

## Bootstrap atual

Entradas principais identificadas:
- `main/__main__.py`
- `main/main_menu.py`
- `main/boot_entry.py`
- `main/main_full_launcher.py`

## Modulos expostos no menu atual

- RH/Admin
- Plantoes
- Escalas
- Operacional
- Relatorios
- Produtividade
- Analise de Dados

## Situacao do banco atual

Base principal:
- `main/grom_database.sqlite3`

Achados relevantes:
- integridade do SQLite atual esta preservada;
- caminho de banco aparece em multiplos helpers;
- ha duplicacao estrutural em cartorios, estatisticas e analise;
- relacionamentos muitas vezes sao implicitos, nao por FK real;
- existem dados sensiveis em tabelas operacionais e cadastrais.

## Principais tabelas por dominio

RH/Escala:
- `funcionarios`
- `cargos`
- `afastamentos`
- `feriados`
- `escala_mensal`
- `plantoes_externos`
- `plantoes_funcionarios`
- `delegados_externos`

Produtividade:
- `cartorios`
- `cartorio_status_hist`
- `cartorio_responsavel_hist`
- `estat_cartorio_mensal`
- `prod_flagrantes`
- `prod_event_log`

Analise:
- `analise_ocorrencias`
- `analise_ocorrencias_extra`
- `analise_change_log`
- `__excel_import_batches`
- `__excel_import_changes`
- `__raw_excel_batches`
- `__raw_excel_rows`

Operacional:
- `mandados`
- `objetos_apreendidos`
- `oper_objetos_apreendidos`
- `oper_objetos_locais`

## Riscos tecnicos identificados

- acesso direto a banco por multiplos modulos;
- mistura de UI, regra de negocio e persistencia;
- senhas e credenciais legadas expostas no modelo atual;
- duplicidade de tabela e estrutura em transicao;
- relatorios fragmentados em varios motores;
- forte dependencia de Windows e de caminhos absolutos.

## Decisao de fronteira para a web

Nao vamos replicar pastas legadas.
Vamos converter o sistema atual em dominios canonicos:
- Access
- Rh
- Escalas
- Produtividade
- Analise
- Operacional
- Relatorios

