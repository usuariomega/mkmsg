# 🚀 MK-MSG: Integração Inteligente MK-Auth & WhatsApp

O **MK-MSG** é uma solução profissional e automatizada para provedores de internet que utilizam o **MK-Auth**. Ele simplifica a comunicação com seus clientes, enviando notificações de cobrança, lembretes de vencimento e confirmações de pagamento diretamente via WhatsApp.

---

## ✨ Visão Geral das Funcionalidades (Screenshots)

Apresentamos as principais telas do sistema, organizadas por módulo para facilitar a compreensão das funcionalidades.
			 
																																																				 

### 1. Gestão Inteligente de Títulos

Visualize e gerencie o status de todos os títulos (boletos/faturas) dos seus clientes em uma única tela. A navegação por abas permite filtrar rapidamente entre títulos **No Prazo**, **Vencidos** e **Pagos**.

#### Títulos No Prazo

*Tela que exibe os títulos com vencimento futuro, prontos para o envio de lembretes preventivos.*
<img width="1420" height="995" alt="noprazo" src="https://github.com/user-attachments/assets/13f14ab2-ef1a-4a9c-b28a-a657b91ad76d" />
<br><br>

#### Títulos Vencidos
*Tela dedicada ao acompanhamento de títulos em atraso, essencial para a régua de cobrança.*
<img width="1420" height="995" alt="vencido" src="https://github.com/user-attachments/assets/4f199311-89e0-475d-b8cb-5a5fb47fed0e" />
<br><br>


#### Títulos Pagos
*Confirmação visual dos títulos que já foram quitados, garantindo que o cliente receba a confirmação de pagamento.*
<img width="1420" height="995" alt="pago" src="https://github.com/user-attachments/assets/cc1d397f-a142-4ee0-85bf-279c5c127624" />
<br><br>


#### Envio em massa
*Permite enviar mensagens em massa para um grupo de clientes ou para todos. É possivel salvar a lista de clientes e de mensagens.*
<img width="1420" height="1472" alt="emmassa" src="https://github.com/user-attachments/assets/949e54df-4adc-4c20-8539-53610269ef0e" />

<br><br>

### 2. Configuração e Personalização de Mensagens

Defina o conteúdo exato das mensagens que serão enviadas para cada situação (No Prazo, Vencido e Pago), utilizando variáveis dinâmicas do sistema.

#### Configuração de Mensagens
*Interface intuitiva para edição das mensagens, com pré-visualização em tempo real do WhatsApp.*
<img width="1420" height="2375" alt="confmsg" src="https://github.com/user-attachments/assets/d584db69-b0ee-4f6d-8363-4d05a84169cf" />
<br><br>


### 3. Dashboard e Configurações do Sistema

Gerencie a conexão com a API do WhatsApp e configure os parâmetros globais de envio.

#### Configurações Gerais
*Ajuste os tempos de pausa entre envios, os dias específicos para disparo de cada tipo de mensagem e os horários de execução do *daemon*.*
<img width="1420" height="2837" alt="confgeral" src="https://github.com/user-attachments/assets/080832a5-149c-4758-b302-d738a343eecd" />
<br><br>



#### Dashboard da API WhatsApp
*Conecte seu número de WhatsApp de forma segura via QR Code e monitore o status da conexão e a fila de envio.*
<img width="1585" height="962" alt="whatsappapi" src="https://github.com/user-attachments/assets/7e0147fe-267f-4d32-abdb-ab47dd526c7c" /><br><br>


---

## 💡 Funcionalidades Principais

*   ✅ **Instalação 100% Automatizada**: Script inteligente que configura tudo para você.
*   🤖 **Envios Automáticos**: Envios programados por data e hora para títulos no prazo, vencidos e pagos.
*   📊 **Logs Detalhados**: Histórico completo de envios organizado por mês e categoria.
*   🎨 **Interface Responsiva**: Dashboard moderno que funciona perfeitamente no celular e PC.

---

## 🛠️ Pré-requisitos

*   Servidor com **Ubuntu, Debian ou Linux Mint** (IP Privado/Rede Local).
*   Acesso SSH ao seu servidor **MK-Auth**.
*   Conexão com a Internet.

---

## 🚀 Como Instalar (Rápido e Fácil)

1.  Acesse o terminal do seu servidor (VM onde ficará o MK-MSG).
2.  Execute o comando abaixo:

```bash
curl -O https://raw.githubusercontent.com/usuariomega/mkmsg/main/install/install_mkmsg.sh
chmod +x install_mkmsg.sh
sudo ./install_mkmsg.sh
```

### 📝 O que o instalador fará por você:

*   Instalará todas as dependências (Apache, PHP, etc).
*   Configurará o acesso remoto ao banco de dados do seu **MK-Auth** via SSH.
*   Gerará um **Token de Segurança** exclusivo de 20 caracteres.
*   Instalará e configurará a **API do WhatsApp** localmente.
*   Configurará o agendamento automático de mensagens (opcional).

---

## ⚙️ Pós-Instalação

Após o término do script, você receberá os links de acesso:

*   **Painel MK-MSG**: `http://seu-ip/mkmsg`
*   **Dashboard WhatsApp**: `http://seu-ip:8000` (Para ler o QR Code)

> **Dica**: No primeiro acesso, conecte seu WhatsApp no Dashboard da API para começar a disparar as mensagens.

---

## 🤝 **Gostou do projeto? Deixe uma estrela no repositório!**
