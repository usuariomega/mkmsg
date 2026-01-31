# 🚀 MK-MSG: Integração Inteligente MK-Auth & WhatsApp

O **MK-MSG** é uma solução profissional e automatizada para provedores de internet que utilizam o **MK-Auth**. Ele simplifica a comunicação com seus clientes, enviando notificações de cobrança, lembretes de vencimento e confirmações de pagamento diretamente via WhatsApp.

---

## ✨ Visão Geral das Funcionalidades (Screenshots)

Apresentamos as principais telas do sistema, organizadas por módulo para facilitar a compreensão das funcionalidades.
			 
																																																				 

### 1. Gestão Inteligente de Títulos

Visualize e gerencie o status de todos os títulos (boletos/faturas) dos seus clientes em uma única tela. A navegação por abas permite filtrar rapidamente entre títulos **No Prazo**, **Vencidos** e **Pagos**.

#### Títulos No Prazo

*Tela que exibe os títulos com vencimento futuro, prontos para o envio de lembretes preventivos.*
<img width="1585" height="995" alt="noprazo" src="https://github.com/user-attachments/assets/2e8725ba-47ac-451f-8b5c-692a4f26b31e" /><br><br>

#### Títulos Vencidos
*Tela dedicada ao acompanhamento de títulos em atraso, essencial para a régua de cobrança.*
<img width="1585" height="995" alt="vencidos" src="https://github.com/user-attachments/assets/5d69f293-aa79-47c1-8bde-9a2050f0494b" /><br><br>


#### Títulos Pagos
*Confirmação visual dos títulos que já foram quitados, garantindo que o cliente receba a confirmação de pagamento.*
<img width="1585" height="995" alt="pagos" src="https://github.com/user-attachments/assets/ee727ddf-3e41-4541-9d24-2d00a43b3c6d" /><br><br>


### 2. Configuração e Personalização de Mensagens

Defina o conteúdo exato das mensagens que serão enviadas para cada situação (No Prazo, Vencido e Pago), utilizando variáveis dinâmicas do sistema.

#### Configuração de Mensagens
*Interface intuitiva para edição das mensagens, com pré-visualização em tempo real do WhatsApp.*
<img width="1585" height="2392" alt="confmsg" src="https://github.com/user-attachments/assets/9cf076e1-cde6-4655-a32e-023d3ab7dab6" /><br><br>


### 3. Dashboard e Configurações do Sistema

Gerencie a conexão com a API do WhatsApp e configure os parâmetros globais de envio.

#### Configurações Gerais
*Ajuste os tempos de pausa entre envios, os dias específicos para disparo de cada tipo de mensagem e os horários de execução do *daemon*.*
<img width="1585" height="2398" alt="confgeral" src="https://github.com/user-attachments/assets/ddc34b62-faae-4a70-9b92-a336916cad76" /><br><br>



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
wget https://raw.githubusercontent.com/usuariomega/mkmsg/main/install/install_mkmsg.sh && sudo bash install_mkmsg.sh
```

### 📝 O que o instalador fará por você:

*   Instalará todas as dependências (Apache, PHP, SQLite, etc).
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
