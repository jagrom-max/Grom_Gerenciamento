# Plano de Migracao

## Premissas

- O GROM Python continua operacional durante toda a transicao.
- Nenhum corte sera feito sem rodada de dupla validacao.
- O banco SQLite atual nao sera descartado antes da homologacao final.
- A migracao sera orientada por dominio, nao por pasta ou por tela.

## Estrategia geral

Havera coexistencia controlada entre:
- sistema Python atual;
- banco atual em SQLite;
- nova plataforma web;
- banco canonico em PostgreSQL.

## Ondas recomendadas

### Onda 0 - Fundacao comum

Entregas:
- autenticacao;
- usuarios e perfis;
- permissoes por modulo;
- auditoria;
- logs;
- fila;
- relatorios padrao;
- infraestrutura base.

Critico porque:
- sem isso, qualquer modulo web nasceria fraco em seguranca e governanca.

### Onda 1 - Produtividade piloto

Escopo:
- cartorios;
- historico de status e responsavel;
- estatisticas mensais;
- flagrantes;
- confirmacao manual de sugestoes da analise.

Motivo:
- e o ponto de dor mais atual;
- ja esta mais pacotizado que outras areas;
- permite validar permissao por cartorio e relatorio institucional cedo.

### Onda 2 - RH/Admin

Escopo:
- funcionarios;
- cargos;
- afastamentos;
- delegados externos.

Objetivo:
- tirar dados cadastrais e senhas legadas do modelo atual;
- preparar base limpa para escalas.

### Onda 3 - Escalas e Plantoes

Escopo:
- feriados;
- plantoes externos;
- escalas mensais;
- fechamento e distribuicao.

### Onda 4 - Operacional

Escopo:
- mandados;
- objetos apreendidos;
- relatorios operacionais.

### Onda 5 - Analise de Dados

Escopo:
- staging de importacao;
- consolidacao de Excel;
- filas de sugestao;
- pesquisa;
- exportacoes.

### Onda 6 - Corte final

Condicoes para desligar o Python:
- todos os modulos homologados;
- conferencias de dados aprovadas;
- backups automatizados e restore testado;
- logs e monitoracao ativos;
- usuarios treinados;
- relatorios validados;
- contingencia de rollback documentada.

## Regra de coexistencia

Durante a migracao:
- o Python continua sendo a referencia operacional dos modulos ainda nao migrados;
- o modulo web entra primeiro em homologacao paralela;
- so depois de validado, aquele dominio passa a escrever no PostgreSQL como fonte principal.

## Estrategia de dados

1. Extrair snapshot do SQLite.
2. Normalizar em staging.
3. Gerar mapa de chaves antigas x novas.
4. Validar contagens, somas e amostras.
5. Carregar no PostgreSQL.
6. Rodar relatorios comparativos.
7. Liberar homologacao funcional.

## O que nao fazer

- nao migrar tudo em uma virada unica;
- nao copiar tabelas duplicadas sem saneamento;
- nao manter senhas legadas em texto;
- nao permitir dupla escrita sem trilha de auditoria;
- nao levar para a web os caminhos locais, `TEMP`, `Downloads` ou `os.startfile`.

