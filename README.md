# MK-MSG: Integração Inteligente MK-Auth & WhatsApp

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


---


### 2. 📨 Envio em Massa

Envie mensagens para grupos específicos de clientes ou para toda a sua base — inclusive para contatos externos ao MK-Auth. É possível salvar e reutilizar listas de clientes e mensagens.

**Funcionalidades do envio em massa:**
- ✅ Envio para múltiplos contatos de forma simples
- 💾 Salvar e carregar listas de clientes e mensagens
- ✏️ Editar listas de clientes e mensagens diretamente pelo painel
- 📁 Importar contatos no formato **vCard (.vcf)**
- 🔍 Pesquisa de contatos em tempo real dentro da lista
<img width="1420" height="1472" alt="emmassa" src="https://github.com/user-attachments/assets/949e54df-4adc-4c20-8539-53610269ef0e" />


---


### 3. Configuração e Personalização de Mensagens

Defina o conteúdo exato das mensagens que serão enviadas para cada situação (No Prazo, Vencido e Pago), utilizando variáveis dinâmicas do sistema.

<img width="1420" height="3048" alt="confmsg" src="https://github.com/user-attachments/assets/f2b23a6e-abc9-488e-beb2-a3f3919e51cb" />

---

### 4. ⚙️ Configurações Gerais e Agendamento

Controle total sobre o comportamento dos envios automáticos:

- ⏱️ **Tempos de pausa** entre envios para evitar bloqueios
- 📅 **Dias específicos** para disparo de cada tipo de mensagem
- 🕐 **Horários de execução** configuráveis do daemon
- 📆 **Envio em dias úteis ou todos os dias** — escolha por tipo de mensagem
- 🔢 **Dias antes do vencimento** para envio preventivo (suporta valor zero = no mesmo dia)
- 📵 **Envio para clientes bloqueados** — configurável com base na data de vencimento + dias para corte

<img width="1420" height="3143" alt="confgeral" src="https://github.com/user-attachments/assets/b19da320-c60c-4ceb-a43c-58efb0b6857d" />
<br><br>

---

### 5. 📱 Dashboard da API WhatsApp

Conecte seu número de WhatsApp de forma segura via QR Code e monitore o status da conexão e a fila de envio em tempo real.

<img width="1420" height="890" alt="13" src="https://github.com/user-attachments/assets/25cf4918-cbfa-4590-b9b5-acdf178afc04" />

---

### 6. 🔔 Alertas via Telegram

Configure um bot do Telegram para receber alertas automáticos quando a API do WhatsApp ficar **offline**, garantindo que você nunca perca um envio sem saber.

**Recursos do alerta Telegram:**
- 🤖 Configuração do bot token e chat id
- ✅ Botão de **teste** para validar as configurações
- 📅 **Agendamento de verificação configurável** pelo painel web (ex: "A cada 20 minutos, 7h, 11h, das 15h às 20h, todos os dias")
- 📌 Exemplos dinâmicos na interface para facilitar a configuração

---

### 7. 🔗 Integração da API do WhatsApp com outros sistemas

O MK-MSG agora suporta integração com **outros sistemas que utilizam o padrão Evolution API versão 2**, não se limitando à API interna. Configure apontando para qualquer instância compatível usando:

- **Nome da instância:** `default` (ou qualquer outro valor — este campo é ignorado)
- **Token:** utilize o mesmo token gerado durante a instalação

Isso permite usar o MK-MSG com qualquer sistema de WhatsApp API que seja compatível com o padrão Evolution API v2.

Exemplo de integração com o Mk-Auth:
<img width="1140" height="698" alt="16" src="https://github.com/user-attachments/assets/4ae9fded-e2fb-4792-a18f-9bd51c39fd0a" />


---

## 💡 Funcionalidades Principais

*   ✅ **Instalação 100% Automatizada**: Script inteligente que configura tudo para você.
*   🤖 **Envios Automáticos**: Envios programados por data e hora para títulos no prazo, vencidos e pagos.
*   📊 **Logs Detalhados**: Histórico completo de envios organizado por mês e categoria.
*   🎨 **Interface Responsiva**: Dashboard moderno que funciona perfeitamente no celular e PC.
*   📁 **Envio em Massa** —  Envie mensagens em massas e importe contatos externos no formato `.vcf`
*   🔔 **Alertas por Telegram** — Monitoramento proativo do status da API, se o WhatsApp ficar offline, receba uma notificação via Telegram.
*   🔗 **Multi-plataforma** — Compatível com padrão do Evolution API v2 para uso da API em outros sistemas.
*   📵 **Aviso de Corte** — Envio automático de aviso antes do bloqueio do cliente.

---

## 🛠️ Pré-requisitos

*   Servidor com **Ubuntu 24 ou Debian 13**.
*   Acesso SSH ao seu servidor **MK-Auth** (apenas se for usar o sistema Web MK-MSG, não é necessário se for usar somente a API do WhatsApp).
*   Conexão com a Internet.

---

## 🚀 Como Instalar

1.  Acesse o terminal do seu servidor (VM onde ficará o MK-MSG).
2.  Execute o comando abaixo:

```bash
curl -O https://raw.githubusercontent.com/usuariomega/mkmsg/main/install/install_mkmsg.sh
chmod +x install_mkmsg.sh
./install_mkmsg.sh
```

### 📝 O que o instalador fará por você:

*   Instalará todas as dependências (Apache, PHP, etc).
*   Gerará um **Token de Segurança** exclusivo de 20 caracteres.
*   Instalará e configurará a **API do WhatsApp** localmente.
*   Configurará o agendamento automático de mensagens.

---

## ⚙️ Pós-Instalação

Após o término do script, você receberá os links de acesso:

*   **Painel MK-MSG**: `http://seu-ip/mkmsg`
*   **Dashboard WhatsApp**: `http://seu-ip:8000` (Para ler o QR Code)

> **Dica**: No primeiro acesso, conecte seu WhatsApp no Dashboard da API para começar a disparar as mensagens.

---

## ⚠️ Informações Importantes 

*   O sistema usa o **Nome Resumido** como campo de nome para enviar as mensagens. 
*   O sistema usa o **Celular** para o envio (não confundir com o campo "Telefone").
*   Use o o link `https://copiaecola.net/?pix=%copiacola%` para enviar links PIX clicáveis (copiar o PIX).
*   Este sistema foi testado com **Efí Bank (Gerencianet)**, **Sicoob** e **Galaxpay**.   
*   Poderá funcionar com outros bancos que usem as mesmas tabelas do banco de dados, porém não foi testado. 

---

## 🤝 **Gostou do projeto? Deixe uma estrela no repositório!**
