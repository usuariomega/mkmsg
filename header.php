<?php
session_start();
include 'config.php';
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <title>MK-MSG</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <style>
    /* ========================================
       VARIÁVEIS CSS (Design System)
       ======================================== */
    :root {
        --primary: #00b32b;
        --primary-hover: #009624;
        --primary-light: #e6f7ed;
        --secondary: #003fff;
        --secondary-hover: #0033cc;
        --secondary-light: #e6edff;
        --tertiary: #395dca;
        --tertiary-hover: #2d4aa3;
        --danger: #dc3545;
        --danger-hover: #c82333;
        --danger-light: #f8d7da;
        --warning: #ffc107;
        --success: #28a745;
        --text-primary: #2c3e50;
        --text-secondary: #6c757d;
        --text-light: #95a5a6;
        --bg-body: #f8f9fa;
        --bg-white: #ffffff;
        --bg-light: #f1f3f5;
        --border: #dee2e6;
        --border-light: #e9ecef;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --radius-sm: 4px;
        --radius-md: 8px;
        --radius-lg: 12px;
        --transition: all 0.2s ease;
        --neutral-btn: #6c757d;
        --neutral-btn-hover: #5a6268;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Inter', sans-serif;
        font-size: 16px;
        color: var(--text-primary);
        background-color: var(--bg-body);
        line-height: 1.5;
    }

    .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 20px; }

    .card {
        background-color: var(--bg-white);
        border-radius: var(--radius-md);
        padding: 20px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        margin-bottom: 20px;
    }

    .menu {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        justify-content: space-between;
        align-items: center;
    }

    .button, .button2, .button3 {
        padding: 12px 24px;
        height: 48px;
        border: 2px solid transparent;
        border-radius: var(--radius-md);
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        text-decoration: none;
    }

    .button { background-color: var(--primary); color: white; }
    .button:hover { background-color: var(--primary-hover); transform: translateY(-2px); }
    .button2 { background-color: var(--secondary); color: white; }
    .button2:hover { background-color: var(--secondary-hover); transform: translateY(-2px); }
    .button3 { background-color: var(--tertiary); color: white; }
    .button3:hover { background-color: var(--tertiary-hover); transform: translateY(-2px); }

    select, input[type="text"], input[type="number"], input[type="time"], textarea {
        height: 48px;
        padding: 12px 16px;
        border: 2px solid var(--border);
        border-radius: var(--radius-md);
        font-size: 15px;
        background-color: var(--bg-white);
        color: var(--text-primary);
        transition: var(--transition);
    }

    select:focus, input:focus, textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-light);
    }

    /* ========================================
       TABELA CUSTOMIZADA
       ======================================== */
    .custom-table { width: 100%; border-collapse: collapse; background: white; }
    .custom-table th { 
        background-color: var(--bg-light); 
        padding: 15px; 
        text-align: left; 
        font-weight: 700; 
        border-bottom: 2px solid var(--border); 
    }
    /* Ajuste de links no cabeçalho */
    .custom-table th a {
        color: #000000 !important;
        text-decoration: none !important;
    }
    .custom-table td { 
        padding: 12px 15px; 
        border-bottom: 1px solid var(--border-light);
        font-size: 14px; /* Tamanho da fonte das linhas */
    }
    .custom-table tbody tr:hover {
        background-color: #f9f9f9;
    }

    /* ========================================
       CHECKBOX (25px x 25px) - Cor Neutra
       ======================================== */
    .check {
        width: 25px !important;
        height: 25px !important;
        cursor: pointer;
        accent-color: var(--text-secondary); /* Cor neutra conforme solicitado */
        vertical-align: middle;
    }

    /* ========================================
       PAGINAÇÃO (Padding-bottom 30px)
       ======================================== */
    .pagination {
        display: flex;
        gap: 5px;
        justify-content: center;
        flex-wrap: wrap;
        padding-bottom: 30px; /* Espaçamento no final */
    }
    
    .page-link {
        padding: 8px 16px;
        border: 1px solid var(--border);
        border-radius: 4px;
        text-decoration: none;
        color: var(--neutral-btn);
        background: white;
        transition: var(--transition);
    }
    
    .page-link.active {
        background: var(--neutral-btn) !important; /* Cor neutra para página ativa */
        color: white !important;
        border-color: var(--neutral-btn) !important;
    }

    .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
    .badge-success { background-color: #d4edda; color: #155724; }
    .badge-danger { background-color: #f8d7da; color: #721c24; }

    /* ========================================
       OVERLAY DE PROCESSAMENTO (Correção de Cor)
       ======================================== */
    .overlay { 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(0,0,0,0.8); 
        z-index: 9999; 
        display: none; 
        align-items: center; 
        justify-content: center;
        text-align: center;
    }
    /* Garantir que o texto dentro do card do overlay seja visível */
    .overlay .card {
        color: #333333 !important; /* Texto escuro no card branco */
        max-width: 800px;
        margin: 20px auto;
        padding: 30px;
        width: 90%;
    }
    .overlay #overlay-content {
        color: #333333 !important;
        text-align: center; /* Ajustado conforme solicitado */
        max-height: 60vh;
        overflow-y: auto;
    }
    .overlay h3 {
        color: var(--primary);
        margin-bottom: 20px;
    }

    /* ========================================
       ESTILOS ESPECÍFICOS DE PÁGINAS
       ======================================== */
    
    /* Títulos e Textos */
    .title-noprazo { color: var(--secondary); margin-bottom: 8px; }
    .title-vencido { color: var(--danger); margin-bottom: 8px; }
    .title-pago { color: var(--success); margin-bottom: 8px; }
    .title-config { color: var(--tertiary); margin-bottom: 8px; }
    .text-subtitle { color: var(--text-secondary); margin: 0; }
    
    /* Utilitários */
    .mb-3 { margin-bottom: 20px; }
    .mt-3 { margin-top: 20px; }
    .w-100 { width: 100%; }
    .text-center { text-align: center; }
    .p-0 { padding: 0 !important; }
    .overflow-x-auto { overflow-x: auto; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    
    /* Botões Pequenos */
    .btn-small { height: 30px; padding: 0 10px; font-size: 12px; margin-left: 10px; }
    
    /* Formulários e Busca */
    .search-container { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .resp-search { max-width: 300px; flex-grow: 1; }
    .btn-search { height: 48px; padding: 0 20px; background-color: #ffffff !important; border-color: #dee2e6 !important; }
    .btn-search:hover { background-color: var(--neutral-btn-hover) !important; transform: translateY(-2px); }
    .select-limit { width: 80px; }
    .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary); }
    .form-input-full { width: 100%; }
    
    /* Configuração de Mensagens */
    .container-conf { max-width: 1200px; margin: 0 auto; } /* Removido padding conforme solicitado */
    .msg-section { margin-bottom: 30px; background: var(--bg-white); border-radius: var(--radius-md); box-shadow: var(--shadow-md); overflow: hidden; border: 1px solid var(--border-light); }
    .section-header { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%); color: white; padding: 16px 24px; font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 10px; }
    .section-header.vencido { background: linear-gradient(135deg, var(--danger) 0%, var(--danger-hover) 100%); }
    .section-header.pago { background: linear-gradient(135deg, var(--success) 0%, #1e7e34 100%); }
    .flex-layout { display: flex; flex-wrap: wrap; gap: 0; }
    .editor-side { flex: 1; min-width: 350px; padding: 24px; border-right: 1px solid var(--border-light); background: var(--bg-white); }
    .preview-side { flex: 1; min-width: 350px; background-color: #e5ddd5; background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-repeat: repeat; padding: 24px; display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }
    .msg-section textarea { width: 100%; min-height: 180px; font-family: 'Courier New', Courier, monospace; font-size: 14px; line-height: 1.6; resize: vertical; margin-bottom: 12px; }
    .whatsapp-bubble { background: #fff; padding: 10px 14px; border-radius: 8px; position: relative; max-width: 85%; font-size: 14px; line-height: 1.5; box-shadow: 0 1px 2px rgba(0,0,0,0.15); margin-bottom: 8px; word-wrap: break-word; white-space: pre-wrap; }
    .whatsapp-bubble::before { content: ""; position: absolute; width: 0; height: 0; border-top: 10px solid transparent; border-bottom: 10px solid transparent; border-right: 10px solid #fff; left: -10px; top: 0; }
    .coringas-list { font-size: 13px; color: var(--text-secondary); background: var(--bg-light); padding: 14px; border-radius: var(--radius-md); margin-top: 8px; line-height: 1.7; border: 1px solid var(--border-light); }
    .coringas-list b { color: var(--primary); font-weight: 600; }

    /* Placeholder responsivo via CSS */
    @media (max-width: 768px) {
        .resp-search::placeholder { content: "Buscar..."; }
        .resp-search::-webkit-input-placeholder { content: "Buscar..."; }
    }
    @media (min-width: 769px) {
        .resp-search::placeholder { content: "Buscar nome ou celular..."; }
        .resp-search::-webkit-input-placeholder { content: "Buscar nome ou celular..."; }
    }

    /* ========================================
       RESPONSIVIDADE
       ======================================== */
    @media (max-width: 768px) {
        .menu { flex-direction: column; align-items: stretch; }
        .button, .button2, .button3 { width: 100%; }
        .hide-mobile { display: none !important; }
        .grid-2 { grid-template-columns: 1fr; }
        .editor-side { border-right: none; border-bottom: 1px solid var(--border-light); }
        .flex-layout { flex-direction: column; }
        
        /* Seletor de mês responsivo */
        #formmes, .selectmes { width: 100% !important; }
        .selectmes { width: 100% !important; }

        .search-container {
            width: 100% !important;
            display: flex !important;
            gap: 8px !important;
        }
        input[type="text"][name="search"] {
            width: 100% !important;
            max-width: 100% !important;
            flex: 1;
        }
        input[type="text"][name="search"]::placeholder {
            color: transparent;
        }
        input[type="text"][name="search"]::-webkit-input-placeholder {
            color: transparent;
        }
    }
    </style>
</head>
<body>

