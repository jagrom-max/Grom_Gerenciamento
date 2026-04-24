# Deploy GROM Web via HTTP (sem SSH)

## Situação

A porta 22 (SSH) da VPS ainda está bloqueada na Oracle Cloud. Mas as portas 80/443 (HTTP/HTTPS) estão abertas. 

Vamos contornar: você roda um servidor HTTP no seu PC, a VPS faz download do script e código via HTTP, e executa o bootstrap tudo sozinho.

---

## Passo 1: Iniciar servidor de deploy no seu PC

Abra **PowerShell** como Administrador no seu PC:

```powershell
cd C:\grom_gerenciamento_final\grom_web_php
.\scripts\Deploy-ToVPS-HTTP.ps1 `
  -VpsIp 137.131.241.192 `
  -Domain grom.seg.br `
  -CertbotEmail jagrom@gmail.com `
  -AdminPassword (ConvertTo-SecureString '03031981Gr**' -AsPlainText -Force)
```

Vai aparecer assim:

```
>>> Deploy GROM Web via HTTP
    OK: Arquivo compactado...
    OK: Bootstrap script copiado...
>>> Iniciando servidor HTTP na porta 8888...
    Servidor rodando...

=============================================
 COPIE E EXECUTE ESTE COMANDO NA VPS:
=============================================

sudo bash -c 'curl -fsSL http://SEU_IP_LOCAL:8888/bootstrap.sh | GROM_DOMAIN='grom.seg.br' ... bash'

=============================================
```

**Deixe este terminal aberto.**

---

## Passo 2: Descobrir seu IP local (Windows)

Em outro PowerShell:

```powershell
ipconfig | Select-String "IPv4"
```

Procure algo como `192.168.x.x` ou `10.x.x.x`.

---

## Passo 3: Executar o deploy na VPS via Console Serial Oracle

1. Acesse a Oracle Cloud Console
2. **Computação → Instâncias**
3. Clique em sua instância
4. Menu esquerdo: **Console serial**
5. Aguarde conectar

Na console, cole (com o IP do seu PC):

```bash
sudo bash -c 'curl -fsSL http://SEU_IP_LOCAL:8888/bootstrap.sh | GROM_DOMAIN=grom.seg.br GROM_CERTBOT_EMAIL=jagrom@gmail.com GROM_ADMIN_PASSWORD=03031981Gr** bash'
```

**Exemplo completo:**
```bash
sudo bash -c 'curl -fsSL http://192.168.1.50:8888/bootstrap.sh | GROM_DOMAIN=grom.seg.br GROM_CERTBOT_EMAIL=jagrom@gmail.com GROM_ADMIN_PASSWORD=03031981Gr** bash'
```

Pressione **Enter**.

---

## Passo 4: Monitorar o deploy

O script vai rodar na VPS e imprimir passos (1/11, 2/11, ... 11/11).

**Leva cerca de 8-10 minutos.**

No seu PC, o servidor HTTP vai mostrar:
```
[16:45:32] Requisicao: GET /bootstrap.sh
    [DEPLOY INICIADO] Bootstrap enviado para a VPS

[16:45:45] Requisicao: GET /grom_deploy.tar.gz
    [TAR ENVIADO] Repositorio (45.2MB)
```

---

## Passo 5: Validar acesso final

Assim que terminar, acesse:

```
https://grom.seg.br/login
```

Faça login com:
- **Usuário:** admin
- **Senha:** 03031981Gr**

---

## Troubleshooting

**"Connection refused" na console da VPS:**
- Seu firewall do PC pode estar bloqueando. Tente desativar ou liberar porta 8888.
- Verifique se usou o IP local correto (não localhost).

**Timeout no `curl`:**
- Verifique se a porta 8888 do seu PC está acessível da VPS (mesma rede? VPN?).
- Teste: na VPS console, `curl http://SEU_IP:8888/` deve retornar HTML.

**Deploy falha na VPS:**
- Verifique logs: `docker compose logs app` (após deploy).
- Verifique `.env.production`: `cat /opt/grom/grom_web_php/infra/.env.production`

---

## Credenciais geradas

Após conclusão, as credenciais estão em:
```
/root/grom-credenciais.txt
```

Leia via console:
```bash
sudo cat /root/grom-credenciais.txt
```

Copie e guarde em local seguro.

---

**Observação:** Este método evita bloqueios de SSH e permite deploy completo via HTTP. É seguro pois o servidor HTTP fecha após o deploy.
