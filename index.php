<?php
require_once __DIR__ . '/php/csrf.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EmpTrack - Employee Skill Tracking System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg-base: #030712;
            --bg-surface: #0a0f1d;
            --navy-deep: #060b18;
            --navy-card: rgba(10, 18, 38, 0.72);
            --electric-blue: #0284c7;
            --cyan-glow: #06b6d4;
            --teal-bright: #14b8a6;
            --emerald-glow: #10b981;
            --emerald-highlight: #34d399;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --card-border: rgba(45, 212, 191, 0.35);
            --card-border-glow: rgba(6, 182, 212, 0.65);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
            overflow-x: hidden;
            background-color: var(--bg-base);
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            position: relative;
        }

        /* 3D Cinematic Environment Background */
        .cinematic-environment {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: 
                radial-gradient(circle at 82% 28%, rgba(6, 182, 212, 0.18) 0%, transparent 45%),
                radial-gradient(circle at 18% 75%, rgba(16, 185, 129, 0.14) 0%, transparent 40%),
                radial-gradient(circle at 50% -10%, rgba(2, 132, 199, 0.22) 0%, transparent 50%),
                linear-gradient(180deg, #030712 0%, #060c1c 65%, #02040a 100%);
            z-index: 1;
            overflow: hidden;
            pointer-events: none;
        }

        /* Subtle Grid Horizon */
        .grid-horizon {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 45vh;
            background-image: 
                linear-gradient(to right, rgba(6, 182, 212, 0.05) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(6, 182, 212, 0.05) 1px, transparent 1px);
            background-size: 60px 40px;
            transform: perspective(600px) rotateX(65deg);
            transform-origin: bottom center;
            opacity: 0.65;
            mask-image: linear-gradient(to top, rgba(0,0,0,1) 15%, transparent 95%);
            -webkit-mask-image: linear-gradient(to top, rgba(0,0,0,1) 15%, transparent 95%);
        }

        /* Ambient Volumetric Glow Blobs */
        .ambient-glow-1 {
            position: absolute;
            top: 15%;
            right: 12%;
            width: 650px;
            height: 650px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.22) 0%, rgba(14, 165, 233, 0.08) 50%, transparent 75%);
            filter: blur(80px);
            animation: pulseGlow 10s ease-in-out infinite alternate;
        }

        .ambient-glow-2 {
            position: absolute;
            bottom: 5%;
            right: 30%;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.16) 0%, transparent 70%);
            filter: blur(90px);
            animation: pulseGlow 14s ease-in-out infinite alternate-reverse;
        }

        .ambient-glow-3 {
            position: absolute;
            top: 25%;
            left: -5%;
            width: 550px;
            height: 550px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(2, 132, 199, 0.15) 0%, transparent 70%);
            filter: blur(100px);
        }

        @keyframes pulseGlow {
            0% { transform: scale(0.92) translate(0, 0); opacity: 0.7; }
            100% { transform: scale(1.1) translate(-20px, 20px); opacity: 1; }
        }

        /* Main Page Grid Layout */
        .page-wrapper {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.2rem 4.5rem;
        }

        /* Top Brand Header */
        .brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 20;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 1.1rem;
            text-decoration: none;
        }

        .logo-symbol {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #0284c7 0%, #06b6d4 50%, #10b981 100%);
            padding: 2px;
            box-shadow: 0 0 25px rgba(6, 182, 212, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-inner {
            width: 100%;
            height: 100%;
            background: #060b18;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #38bdf8;
            font-size: 1.35rem;
            box-shadow: inset 0 0 10px rgba(6, 182, 212, 0.2);
        }

        .logo-text-col {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff;
            line-height: 1.1;
        }

        .brand-subtitle {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #38bdf8;
            margin-top: 0.25rem;
        }

        .header-pills {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .system-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 1rem;
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(45, 212, 191, 0.25);
            border-radius: 100px;
            font-size: 0.78rem;
            color: #cbd5e1;
            backdrop-filter: blur(12px);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--emerald-glow);
            box-shadow: 0 0 10px var(--emerald-glow);
            animation: blinkDot 2s infinite;
        }

        @keyframes blinkDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        /* 2-Column Hero Section */
        .hero-container {
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            gap: 4rem;
            align-items: center;
            margin: auto 0;
            padding: 2rem 0;
            position: relative;
        }

        /* LEFT COLUMN: Modern Typography & Value Pitch */
        .hero-left {
            max-width: 620px;
            z-index: 10;
        }

        .badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.4rem 1.1rem;
            background: linear-gradient(90deg, rgba(6, 182, 212, 0.12) 0%, rgba(16, 185, 129, 0.08) 100%);
            border: 1px solid rgba(45, 212, 191, 0.3);
            border-radius: 100px;
            color: #5eead4;
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 1.75rem;
        }

        .main-headline {
            font-family: 'Outfit', sans-serif;
            font-size: 4.5rem;
            font-weight: 900;
            line-height: 1.05;
            letter-spacing: -1.8px;
            color: #ffffff;
            margin-bottom: 1.5rem;
        }

        .main-headline span.highlight-futures {
            background: linear-gradient(135deg, #06b6d4 0%, #10b981 55%, #34d399 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 45px rgba(20, 184, 166, 0.45);
            display: inline-block;
        }

        .tagline {
            font-size: 1.35rem;
            font-weight: 400;
            color: #94a3b8;
            margin-bottom: 2.5rem;
            line-height: 1.5;
            letter-spacing: -0.2px;
        }

        .stats-strip {
            display: flex;
            align-items: center;
            gap: 2.5rem;
            padding-top: 1.8rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .stat-item h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .stat-item p {
            font-size: 0.82rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 0.2rem;
        }

        /* RIGHT COLUMN: 3D Stage with Floating Blocks & Glass Login Card */
        .hero-right-stage {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            perspective: 1400px;
        }

        /* 3D Floating Acrylic Blocks in Background */
        .floating-block {
            position: absolute;
            background: rgba(10, 20, 42, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(45, 212, 191, 0.35);
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.5),
                0 0 30px rgba(6, 182, 212, 0.25),
                inset 0 1px 1px rgba(255, 255, 255, 0.2);
            border-radius: 18px;
            padding: 1.1rem 1.4rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            z-index: 5;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            pointer-events: auto;
            user-select: none;
        }

        .floating-block:hover {
            transform: translateY(-8px) scale(1.06) !important;
            border-color: rgba(45, 212, 191, 0.7);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.6),
                0 0 45px rgba(6, 182, 212, 0.45);
        }

        .floating-block .block-icon {
            font-size: 1.6rem;
            background: linear-gradient(135deg, #38bdf8 0%, #34d399 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .floating-block .block-label {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            color: #e2e8f0;
        }

        /* Position Stacked 3D Blocks */
        .block-learn {
            top: 2%;
            left: -35px;
            animation: floatBlockA 6.5s ease-in-out infinite;
        }

        .block-track {
            bottom: 8%;
            left: -45px;
            animation: floatBlockB 7.5s ease-in-out infinite;
        }

        .block-grow {
            top: 15%;
            right: -35px;
            animation: floatBlockC 7s ease-in-out infinite;
        }

        .block-grow-bottom {
            bottom: 12%;
            right: -40px;
            animation: floatBlockA 8s ease-in-out infinite;
        }

        @keyframes floatBlockA {
            0%, 100% { transform: translateY(0px) rotate(-2deg); }
            50% { transform: translateY(-12px) rotate(1deg); }
        }

        @keyframes floatBlockB {
            0%, 100% { transform: translateY(0px) rotate(2deg); }
            50% { transform: translateY(-14px) rotate(-1.5deg); }
        }

        @keyframes floatBlockC {
            0%, 100% { transform: translateY(0px) rotate(1deg); }
            50% { transform: translateY(-10px) rotate(3deg); }
        }

        /* 3D Glassmorphism Login Card */
        .glass-login-card {
            width: 100%;
            max-width: 440px;
            background: linear-gradient(145deg, rgba(12, 22, 45, 0.82) 0%, rgba(6, 12, 28, 0.88) 100%);
            backdrop-filter: blur(28px) saturate(180%);
            -webkit-backdrop-filter: blur(28px) saturate(180%);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 2.75rem 2.5rem;
            position: relative;
            z-index: 10;
            box-shadow: 
                0 25px 60px -10px rgba(0, 0, 0, 0.7),
                0 0 45px -5px rgba(6, 182, 212, 0.28),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.22);
            transition: transform 0.25s ease-out, box-shadow 0.3s ease;
            transform-style: preserve-3d;
        }

        .glass-login-card:hover {
            border-color: var(--card-border-glow);
            box-shadow: 
                0 30px 75px -10px rgba(0, 0, 0, 0.8),
                0 0 60px -5px rgba(6, 182, 212, 0.4),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.35);
        }

        .card-specular-sheen {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40%;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, transparent 100%);
            border-radius: 28px 28px 0 0;
            pointer-events: none;
        }

        .card-header-group {
            margin-bottom: 1.85rem;
            text-align: left;
        }

        .card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
            line-height: 1.15;
        }

        .card-subtext {
            font-size: 0.92rem;
            color: var(--text-secondary);
            margin-top: 0.4rem;
        }

        /* Alert notifications */
        .alert-box {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.25rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            backdrop-filter: blur(10px);
        }

        .alert-box.danger {
            background: rgba(225, 29, 72, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.4);
            color: #fecdd3;
        }

        .alert-box.success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(52, 211, 153, 0.4);
            color: #a7f3d0;
        }

        /* Form Inputs */
        .input-group {
            margin-bottom: 1.25rem;
            position: relative;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1.1rem;
            color: var(--text-muted);
            font-size: 1rem;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .glass-input {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.88rem 1rem 0.88rem 2.85rem;
            font-size: 0.95rem;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            transition: all 0.25s ease;
        }

        .glass-input::placeholder {
            color: #475569;
        }

        .glass-input:focus {
            outline: none;
            border-color: var(--cyan-glow);
            background: rgba(15, 23, 42, 0.85);
            box-shadow: 0 0 20px rgba(6, 182, 212, 0.25);
        }

        .glass-input:focus + .input-icon,
        .input-wrapper:focus-within .input-icon {
            color: var(--cyan-glow);
        }

        .password-toggle-btn {
            position: absolute;
            right: 1.1rem;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1rem;
            cursor: pointer;
            padding: 0.2rem;
            transition: color 0.2s ease;
        }

        .password-toggle-btn:hover {
            color: #ffffff;
        }

        /* Utilities row (Remember me & Forgot password) */
        .form-utilities-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }

        .remember-wrapper {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            cursor: pointer;
            color: var(--text-secondary);
            user-select: none;
        }

        .custom-checkbox {
            appearance: none;
            -webkit-appearance: none;
            width: 17px;
            height: 17px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            background: rgba(15, 23, 42, 0.7);
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }

        .custom-checkbox:checked {
            background: var(--teal-bright);
            border-color: var(--teal-bright);
        }

        .custom-checkbox:checked::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 10px;
            color: #ffffff;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .forgot-link {
            color: #38bdf8;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .forgot-link:hover {
            color: #5eead4;
            text-decoration: underline;
        }

        /* Glowing Gradient Sign In Button */
        .btn-glow-submit {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, #0284c7 0%, #06b6d4 45%, #10b981 100%);
            border: none;
            border-radius: 14px;
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            cursor: pointer;
            box-shadow: 0 10px 25px -5px rgba(6, 182, 212, 0.45);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-glow-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px -5px rgba(6, 182, 212, 0.65);
            filter: brightness(1.08);
        }

        .btn-glow-submit:active {
            transform: translateY(0);
        }

        /* Social Divider */
        .social-divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0 1.2rem 0;
            gap: 1rem;
        }

        .divider-line {
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .divider-text {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            white-space: nowrap;
        }

        /* Social Circle Buttons */
        .social-buttons-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.2rem;
            margin-bottom: 1.6rem;
        }

        .btn-social-circle {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .btn-social-circle:hover {
            transform: translateY(-3px) scale(1.08);
            border-color: rgba(6, 182, 212, 0.5);
            background: rgba(30, 41, 59, 0.9);
            box-shadow: 0 8px 20px rgba(6, 182, 212, 0.25);
        }

        .btn-social-circle.google:hover { color: #ea4335; border-color: rgba(234, 67, 53, 0.4); }
        .btn-social-circle.microsoft:hover { color: #00a4ef; border-color: rgba(0, 164, 239, 0.4); }
        .btn-social-circle.github:hover { color: #ffffff; border-color: rgba(255, 255, 255, 0.4); }

        /* Card Footer */
        .card-footer {
            text-align: center;
            font-size: 0.88rem;
            color: var(--text-secondary);
        }

        .signup-link {
            color: #38bdf8;
            font-weight: 600;
            text-decoration: none;
            margin-left: 0.3rem;
            transition: color 0.2s ease;
        }

        .signup-link:hover {
            color: #5eead4;
            text-decoration: underline;
        }

        /* Quick Demo Credentials Bar */
        .demo-credentials-dock {
            margin-top: 1.5rem;
            padding-top: 1.2rem;
            border-top: 1px dashed rgba(255, 255, 255, 0.1);
        }

        .demo-dock-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 0.6rem;
        }

        .demo-pills-row {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .demo-pill {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            font-size: 0.75rem;
            padding: 0.3rem 0.65rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .demo-pill:hover {
            background: rgba(6, 182, 212, 0.18);
            border-color: rgba(6, 182, 212, 0.4);
            color: #5eead4;
            transform: translateY(-1px);
        }

        /* Bottom Footer */
        .page-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            color: var(--text-muted);
            z-index: 20;
            padding-top: 1rem;
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: var(--text-secondary);
        }

        /* Responsive Breakpoints */
        @media (max-width: 1200px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 3.5rem;
            }
            .hero-left {
                max-width: 100%;
                text-align: center;
            }
            .badge-tag, .stats-strip {
                justify-content: center;
            }
            .floating-block {
                display: none; /* Hide floating blocks on compact screens to prevent overlap */
            }
            .page-wrapper {
                padding: 1.75rem 2rem;
            }
        }

        @media (max-width: 768px) {
            .main-headline {
                font-size: 3.2rem;
            }
            .tagline {
                font-size: 1.1rem;
            }
            .stats-strip {
                gap: 1.5rem;
            }
            .glass-login-card {
                padding: 2rem 1.5rem;
            }
            .page-footer {
                flex-direction: column;
                gap: 0.8rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <!-- Cinematic 3D Environment Background & Volumetric Glows -->
    <div class="cinematic-environment">
        <div class="ambient-glow-1"></div>
        <div class="ambient-glow-2"></div>
        <div class="ambient-glow-3"></div>
        <div class="grid-horizon"></div>
    </div>

    <!-- Main Page Layout -->
    <div class="page-wrapper">

        <!-- Top Header Navigation -->
        <header class="brand-header">
            <a href="index.php" class="brand-logo" title="EmpTrack - Employee Skill Tracking System">
                <div class="logo-symbol">
                    <div class="logo-inner">
                        <i class="fas fa-compass"></i>
                    </div>
                </div>
                <div class="logo-text-col">
                    <span class="brand-title">EmpTrack</span>
                    <span class="brand-subtitle">Employee Skill Tracking System</span>
                </div>
            </a>

            <div class="header-pills">
                <div class="system-status-badge">
                    <span class="status-dot"></span>
                    <span>System Online &bull; v2.4 Enterprise</span>
                </div>
            </div>
        </header>

        <!-- 2-Column Hero: Typography on Left, 3D Glassmorphism on Right -->
        <main class="hero-container">

            <!-- LEFT COLUMN: Typography & Value Pitch -->
            <section class="hero-left">
                <div class="badge-tag">
                    <i class="fas fa-sparkles"></i>
                    <span>Next-Gen Workforce Intelligence</span>
                </div>

                <h1 class="main-headline">
                    Track Skills<br>
                    Build <span class="highlight-futures">Futures</span>
                </h1>

                <p class="tagline">
                    Empowering people. Enabling growth.
                </p>

                <div class="stats-strip">
                    <div class="stat-item">
                        <h4>98.4%</h4>
                        <p>Skill Readiness</p>
                    </div>
                    <div class="stat-item">
                        <h4>100%</h4>
                        <p>Verified Records</p>
                    </div>
                    <div class="stat-item">
                        <h4>Zero-Trust</h4>
                        <p>CSRF &amp; RBAC Security</p>
                    </div>
                </div>
            </section>

            <!-- RIGHT COLUMN: 3D Stage with Floating Blocks & Glass Login Card -->
            <section class="hero-right-stage" id="stageContainer">

                <!-- Stacked 3D Floating Acrylic Blocks -->
                <div class="floating-block block-learn" title="Analytics & Continuous Learning">
                    <i class="fas fa-chart-line block-icon"></i>
                    <span class="block-label">Learn</span>
                </div>

                <div class="floating-block block-track" title="Team & Skill Tracking">
                    <i class="fas fa-users-gear block-icon"></i>
                    <span class="block-label">Track</span>
                </div>

                <div class="floating-block block-grow" title="Certifications & Career Growth">
                    <i class="fas fa-trophy block-icon"></i>
                    <span class="block-label">Grow</span>
                </div>

                <!-- Floating 3D Glassmorphism Login Card -->
                <div class="glass-login-card" id="loginCard">
                    <div class="card-specular-sheen"></div>

                    <div class="card-header-group">
                        <h2 class="card-title">Welcome Back</h2>
                        <p class="card-subtext">Sign in to continue to EmpTrack</p>
                    </div>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert-box danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span><?php echo htmlspecialchars($_GET['error']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['msg'])): ?>
                        <div class="alert-box success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($_GET['msg']); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Form connects to auth.php with full CSRF protection -->
                    <form action="php/auth.php" method="POST" id="authForm">
                        <?php echo csrf_field(); ?>

                        <!-- Email or Username -->
                        <div class="input-group">
                            <div class="input-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input 
                                    type="text" 
                                    id="username" 
                                    name="username" 
                                    class="glass-input" 
                                    placeholder="Email or Username" 
                                    required 
                                    autocomplete="username"
                                >
                            </div>
                        </div>

                        <!-- Password with toggle eye icon -->
                        <div class="input-group">
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="glass-input" 
                                    placeholder="Password" 
                                    required 
                                    autocomplete="current-password"
                                >
                                <button type="button" class="password-toggle-btn" id="passwordToggle" aria-label="Toggle password visibility">
                                    <i class="far fa-eye" id="toggleEyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="form-utilities-row">
                            <label class="remember-wrapper" for="rememberMe">
                                <input type="checkbox" id="rememberMe" name="remember" class="custom-checkbox">
                                <span>Remember me</span>
                            </label>
                            <a href="#" class="forgot-link" onclick="alert('For security assistance, please reach out to your HR System Administrator at admin@skillcompass.com.'); return false;">
                                Forgot password?
                            </a>
                        </div>

                        <!-- Glowing Gradient Sign In Button -->
                        <button type="submit" class="btn-glow-submit">
                            <span>Sign In</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>

                        <!-- Social Login Divider -->
                        <div class="social-divider">
                            <div class="divider-line"></div>
                            <span class="divider-text">or continue with</span>
                            <div class="divider-line"></div>
                        </div>

                        <!-- Circular Google, Microsoft, GitHub Login Icons -->
                        <div class="social-buttons-row">
                            <a href="#" class="btn-social-circle google" title="Sign in with Google" onclick="alert('Enterprise Single Sign-On (SSO) via Google Workspace is configured for production domains.'); return false;">
                                <i class="fab fa-google"></i>
                            </a>
                            <a href="#" class="btn-social-circle microsoft" title="Sign in with Microsoft 365" onclick="alert('Microsoft Entra ID / Azure AD SSO is enabled.'); return false;">
                                <i class="fab fa-microsoft"></i>
                            </a>
                            <a href="#" class="btn-social-circle github" title="Sign in with GitHub Enterprise" onclick="alert('GitHub OAuth is connected to corporate org.'); return false;">
                                <i class="fab fa-github"></i>
                            </a>
                        </div>

                        <!-- Card Footer -->
                        <div class="card-footer">
                            <span>Don't have an account?</span>
                            <a href="#" class="signup-link" onclick="alert('EmpTrack accounts are provisioned by your corporate HR administrator. Please log in with your company credentials or contact HR.'); return false;">
                                Sign Up
                            </a>
                        </div>

                        <!-- Quick 1-Click Demo Login Dock -->
                        <div class="demo-credentials-dock">
                            <div class="demo-dock-label">Quick 1-Click Demo Logins</div>
                            <div class="demo-pills-row">
                                <button type="button" class="demo-pill" onclick="fillCredentials('admin', 'admin123')">
                                    <i class="fas fa-shield-alt"></i> Admin
                                </button>
                                <button type="button" class="demo-pill" onclick="fillCredentials('hrmanager', 'hr123')">
                                    <i class="fas fa-id-badge"></i> HR
                                </button>
                                <button type="button" class="demo-pill" onclick="fillCredentials('rjohnson', 'manager123')">
                                    <i class="fas fa-briefcase"></i> Manager
                                </button>
                                <button type="button" class="demo-pill" onclick="fillCredentials('jdoe', 'employee123')">
                                    <i class="fas fa-user-check"></i> Employee
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </section>

        </main>

        <!-- Bottom Page Footer -->
        <footer class="page-footer">
            <div>&copy; <?php echo date('Y'); ?> EmpTrack. All rights reserved. Enterprise Skill Tracking Architecture.</div>
            <div class="footer-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Security</a>
                <a href="#">Support</a>
            </div>
        </footer>

    </div>

    <!-- Interactive 3D Perspective Tilt Script -->
    <script>
        // Password Visibility Toggle
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('passwordToggle');
        const toggleEyeIcon = document.getElementById('toggleEyeIcon');

        if (passwordToggle && passwordInput) {
            passwordToggle.addEventListener('click', () => {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                toggleEyeIcon.classList.toggle('fa-eye', !isPassword);
                toggleEyeIcon.classList.toggle('fa-eye-slash', isPassword);
            });
        }

        // 1-Click Demo Fill
        function fillCredentials(user, pass) {
            document.getElementById('username').value = user;
            document.getElementById('password').value = pass;
            // Highlight glow on inputs
            const inputs = [document.getElementById('username'), document.getElementById('password')];
            inputs.forEach(input => {
                input.style.borderColor = '#10b981';
                setTimeout(() => { input.style.borderColor = ''; }, 1200);
            });
        }

        // 3D Tilt Effect for Glass Card
        const card = document.getElementById('loginCard');
        const stage = document.getElementById('stageContainer');

        if (window.matchMedia('(pointer: fine)').matches && card && stage) {
            stage.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const cardX = rect.left + rect.width / 2;
                const cardY = rect.top + rect.height / 2;

                const diffX = (e.clientX - cardX) / 25;
                const diffY = (e.clientY - cardY) / 25;

                // Subtle 3D tilt with depth
                card.style.transform = `perspective(1200px) rotateY(${diffX}deg) rotateX(${-diffY}deg) translateZ(12px)`;
            });

            stage.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1200px) rotateY(0deg) rotateX(0deg) translateZ(0px)';
            });
        }
    </script>
</body>
</html>
