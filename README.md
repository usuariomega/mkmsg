# 🚀 MK-MSG: Integração Inteligente MK-Auth & WhatsApp

O **MK-MSG** é uma solução profissional e automatizada para provedores de internet que utilizam o **MK-Auth**. Ele simplifica a comunicação com seus clientes, enviando notificações de cobrança, lembretes de vencimento e confirmações de pagamento diretamente via WhatsApp.

---

### 📸 Visual do Sistema

| **Painel de Controle** | **Envio de Mensagens** |
|:---:|:---:|
| ![Dashboard](https://github.com/usuariomega/mkmsg/assets/70543919/1b6e63d0-000e-4c11-b502-24325bb34e79) | ![Envio](https://github.com/usuariomega/mkmsg/assets/70543919/732f8471-bff2-40a7-acd2-e8b5f57ce0e8) |

| **Resultado no WhatsApp** | **Leitor de Logs** |
|:---:|:---:|
| ![WhatsApp](https://github.com/usuariomega/mkmsg/assets/70543919/2241b4e7-df87-4111-89ed-5ce4fc035b8c) | ![Logs](https://github.com/usuariomega/mkmsg/assets/70543919/5aad9b05-11b2-4aef-aaaa-e9a3155792c9) |

---

### ✨ Funcionalidades Principais

*   ✅ **Instalação 100% Automatizada**: Script inteligente que configura tudo para você.
*   🤖 **Envios Automáticos (Cron)**: Notificações programadas para títulos no prazo, vencidos e pagos.
*   📊 **Logs Detalhados**: Histórico completo de envios organizado por mês e categoria.
*   🎨 **Interface Responsiva**: Dashboard moderno que funciona perfeitamente no celular e PC.

---

### 🛠️ Pré-requisitos

*   Servidor com **Ubuntu, Debian ou Linux Mint** (IP Privado/Rede Local).
*   Acesso SSH ao seu servidor **MK-Auth**.
*   Conexão com a Internet.

---

### 🚀 Como Instalar (Rápido e Fácil)

1.  Acesse o terminal do seu servidor (VM onde ficará o MK-MSG).
2.  Execute o comando abaixo:

```bash
wget https://raw.githubusercontent.com/usuariomega/mkmsg/refs/heads/main/install/install_mkmsg.sh && sudo bash install_mkmsg.sh
```

#### 📝 O que o instalador fará por você:
*   Instalará todas as dependências (Apache, PHP, SQLite, etc).
*   Configurará o acesso remoto ao banco de dados do seu **MK-Auth** via SSH.
*   Gerará um **Token de Segurança** exclusivo de 20 caracteres.
*   Instalará e configurará a **API do WhatsApp** localmente.
*   Configurará o agendamento automático de mensagens (opcional).

---

### ⚙️ Pós-Instalação

Após o término do script, você receberá os links de acesso:
*   **Painel MK-MSG**: `http://seu-ip/mkmsg`
*   **Dashboard WhatsApp**: `http://seu-ip:8000` (Para ler o QR Code)

> **Dica**: No primeiro acesso, conecte seu WhatsApp no Dashboard da API para começar a disparar as mensagens.

---

### 🤝 **Gostou do projeto? Deixe uma estrela no repositório!**
