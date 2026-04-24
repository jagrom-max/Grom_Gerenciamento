import sqlite3

# Use most recent backup
db = r'c:\grom_gerenciamento_final\_SAFE_BACKUP\RELATORIOS_PDF_V1_20260312_084717\DB_SNAPSHOT\grom_database.SNAPSHOT_20260312_084717.sqlite3'
conn = sqlite3.connect(db)

targets = ['escala_mensal', 'plantoes_externos', 'plantoes_funcionarios', 'delegados_externos', 'funcionarios', 'afastamentos']

for t in targets:
    print(f'\n{"="*60}')
    print(f'TABLE: {t}')
    print(f'{"="*60}')
    try:
        rows_info = conn.execute(f'PRAGMA table_info([{t}])').fetchall()
        for r in rows_info:
            print(f'  col[{r[0]}] {r[1]:<30} {r[2]:<15} NOT NULL={r[3]}  DEFAULT={r[4]}')
        count = conn.execute(f'SELECT COUNT(*) FROM [{t}]').fetchone()[0]
        print(f'  --> {count} registros')
        if count > 0:
            sample = conn.execute(f'SELECT * FROM [{t}] LIMIT 5').fetchall()
            cols = [r[1] for r in rows_info]
            for s in sample:
                row_dict = dict(zip(cols, s))
                print(f'  {row_dict}')
    except Exception as e:
        print(f'  ERROR: {e}')

print('\n=== ESTATISTICAS escala_mensal ===')
try:
    for row in conn.execute('SELECT ano, mes, COUNT(*) as dias FROM escala_mensal GROUP BY ano, mes ORDER BY ano, mes'):
        print(f'  {row[0]}/{row[1]:02d} -> {row[2]} dias')
except Exception as e:
    print(f'  ERROR: {e}')

print('\n=== VALORES DISTINTOS versao ===')
try:
    for row in conn.execute('SELECT DISTINCT versao FROM escala_mensal ORDER BY versao'):
        print(f'  versao={row[0]}')
except Exception as e:
    print(f'  ERROR: {e}')

print('\n=== ALL plantoes_externos ===')
try:
    for row in conn.execute('SELECT * FROM plantoes_externos'):
        print(f'  {row}')
except Exception as e:
    print(f'  ERROR: {e}')

print('\n=== ALL delegados_externos ===')
try:
    for row in conn.execute('SELECT * FROM delegados_externos'):
        print(f'  {row}')
except Exception as e:
    print(f'  ERROR: {e}')

conn.close()
