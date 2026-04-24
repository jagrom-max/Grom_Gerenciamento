# Relatorios e Timbrado

## Objetivo

Unificar a emissao de relatorios em um unico padrao visual e tecnico, preservando a identidade institucional ja consolidada no GROM atual.

## Elementos visuais a preservar

Tokens visuais extraidos do sistema atual:
- fundo claro: `#f5f7fa`
- cor primaria: `#2c3e50`
- hover e apoio: `#34495e`
- campos claros com borda discreta
- uso recorrente de cinza para titulos de bloco e tabela

Assets que devem ser versionados e tratados como oficiais:
- `assets/brasao.png`
- `assets/brasao_pcsp.png`
- `assets/logo_grom.png`
- `assets/marca_dagua.png`
- `main/analise_dados/hub/_assets/TIMBRADO_CONSOLIDADO_BASE.pdf`

## Timbrado institucional

O template canonico do novo sistema deve preservar:
- brasao a esquerda;
- cabecalho institucional da DDM Rio Claro;
- marca d'agua central;
- logo GROM no rodape;
- data/hora e paginacao;
- formato A4;
- margens consistentes para nao colidir com cabecalho e rodape.

## Padrao tecnico novo

O web nao pode depender de:
- `TEMP`
- `Downloads`
- `os.startfile`
- caminhos `C:\\grom_gerenciamento_final`
- multiplos motores de PDF para o mesmo dominio

Padrao alvo:
- HTML/CSS como template visual canonico;
- um unico servico de renderizacao PDF;
- dados injetados por DTO/caso de uso;
- templates versionados;
- saida persistida em storage privado com trilha de emissao.

## Familias de relatorio

1. RH
- funcionarios;
- afastamentos;
- consolidacoes administrativas.

2. Escalas
- escalas mensais;
- plantoes;
- resumos e fechamentos.

3. Produtividade
- cartorios;
- investigacao;
- inqueritos;
- boletins;
- atividades PJ;
- flagrantes por origem e periodo.

4. Analise
- pesquisa por pessoa;
- estatisticas;
- cards;
- exportacoes XLSX.

5. Operacional
- mandados;
- objetos apreendidos;
- consolidacoes operacionais.

## Regra de governanca

- o template A4 passa a ser unico e reutilizavel;
- variacoes serao feitas por bloco de conteudo, nao por reescrever cabecalho;
- nenhum relatorio novo entra em producao sem comparacao com o timbrado oficial.

