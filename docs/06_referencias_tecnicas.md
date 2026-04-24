# Referencias Tecnicas

Base local analisada:
- `main/main_menu.py`
- `main/db_utils.py`
- `main/mod_produtividade/ui_hub.py`
- `main/mod_produtividade/ui_atividades_pj.py`
- `main/mod_produtividade/flagrantes_bridge.py`
- `main/analise_dados/_tools/IMPORTAR_EXCEL_SEGURO_V1F.py`
- `main/print_system/timbrado_relatorios.py`
- `main/print_folha_timbrada.PADRAO_CONSOLIDADO.py`

Documentacao oficial consultada em 2026-03-30:
- Laravel Authentication: https://laravel.com/docs/12.x/authentication
- Laravel Authorization: https://laravel.com/docs/12.x/authorization
- Laravel Queues: https://laravel.com/docs/12.x/queues
- Laravel Task Scheduling: https://laravel.com/docs/12.x/scheduling
- Laravel Logging: https://laravel.com/docs/12.x/logging
- OWASP Authentication Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
- OWASP Authorization Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html
- OWASP Logging Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html
- OWASP Transport Layer Security Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Transport_Layer_Security_Cheat_Sheet.html
- PostgreSQL High Availability, Load Balancing, and Replication: https://www.postgresql.org/docs/current/high-availability.html

Decisao de uso das referencias:
- Laravel para autenticacao, autorizacao, fila, scheduler e logging;
- OWASP como baseline de seguranca de aplicacao;
- PostgreSQL como referencia para continuidade, resiliencia e operacao.

