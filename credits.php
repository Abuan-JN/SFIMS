<?php
/**
 * SFIMS Credits Page
 * 
 * Displays team member information in a professional grid layout
 * with responsive design and interactive hover effects.
 * Includes categorized sections, technology stack, project timeline,
 * and comprehensive contributor profiles.
 */

require_once 'config/database.php';
require_once 'config/app.php';

$page_title = 'Credits';
require_once 'partials/header.php';
?>

<style>
    /* Credits Page Specific Styles */
    .credits-hero {
        background: linear-gradient(135deg, var(--sfims-green) 0%, #2d5a27 100%);
        padding: 80px 20px;
        text-align: center;
        margin-bottom: 50px;
        border-radius: 16px;
        position: relative;
        overflow: hidden;
    }

    .credits-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: radial-gradient(circle at 20% 80%, rgba(74, 222, 128, 0.15) 0%, transparent 50%),
                          radial-gradient(circle at 80% 20%, rgba(74, 222, 128, 0.1) 0%, transparent 50%);
        pointer-events: none;
    }

    .credits-hero::after {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: repeating-linear-gradient(
            45deg,
            transparent,
            transparent 35px,
            rgba(255,255,255,0.03) 35px,
            rgba(255,255,255,0.03) 70px
        );
        animation: patternMove 30s linear infinite;
    }

    @keyframes patternMove {
        0% { transform: translate(0, 0); }
        100% { transform: translate(70px, 70px); }
    }

    .credits-hero h1 {
        font-size: 3rem;
        font-weight: 800;
        color: #ffffff;
        margin-bottom: 15px;
        position: relative;
        z-index: 1;
        text-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    .credits-hero p {
        font-size: 1.2rem;
        color: rgba(255, 255, 255, 0.9);
        max-width: 700px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
        line-height: 1.6;
    }

    .credits-badge {
        display: inline-block;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        padding: 8px 20px;
        border-radius: 30px;
        margin-top: 20px;
        font-size: 0.9rem;
        color: #fff;
        border: 1px solid rgba(255,255,255,0.3);
    }

    .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        padding: 20px 0;
    }

    .team-card {
        background: var(--sfims-card-bg);
        border: 1px solid var(--sfims-border);
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .team-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #4ade80, #22c55e, #16a34a);
        transform: scaleX(0);
        transition: transform 0.4s ease;
    }

    .team-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        border-color: #4ade80;
    }

    .team-card:hover::before {
        transform: scaleX(1);
    }

    .team-photo {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        margin: 0 auto 20px;
        overflow: hidden;
        border: 4px solid var(--sfims-border);
        transition: all 0.4s ease;
        position: relative;
    }

    .team-card:hover .team-photo {
        border-color: #4ade80;
        transform: scale(1.08);
        box-shadow: 0 10px 30px rgba(74, 222, 128, 0.3);
    }

    .team-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s ease;
    }

    .team-card:hover .team-photo img {
        transform: scale(1.15);
    }

    .team-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--sfims-text);
        margin-bottom: 8px;
    }

    .team-role {
        font-size: 0.9rem;
        color: #4ade80;
        font-weight: 600;
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .team-contribution {
        font-size: 0.85rem;
        color: var(--sfims-text);
        opacity: 0.75;
        margin-bottom: 15px;
        font-style: italic;
    }

    .team-bio {
        font-size: 0.9rem;
        color: var(--sfims-text);
        opacity: 0.8;
        line-height: 1.6;
    }

    .team-social {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--sfims-border);
    }

    .team-social a {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--sfims-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--sfims-text);
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 1.1rem;
    }

    .team-social a:hover {
        background: #4ade80;
        color: #1e451e;
        transform: translateY(-4px) scale(1.1);
    }

    .section-title {
        text-align: center;
        margin-bottom: 50px;
        position: relative;
    }

    .section-title::after {
        content: '';
        display: block;
        width: 60px;
        height: 4px;
        background: linear-gradient(90deg, #4ade80, #22c55e);
        margin: 15px auto 0;
        border-radius: 2px;
    }

    .section-title h2 {
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--sfims-text);
        margin-bottom: 10px;
    }

    .section-title p {
        color: var(--sfims-text);
        opacity: 0.7;
        font-size: 1.05rem;
        max-width: 600px;
        margin: 0 auto;
    }

    /* Tech Stack Section */
    .tech-stack-section {
        background: var(--sfims-card-bg);
        border: 1px solid var(--sfims-border);
        border-radius: 16px;
        padding: 40px;
        margin-top: 50px;
    }

    .tech-category {
        margin-bottom: 30px;
    }

    .tech-category:last-child {
        margin-bottom: 0;
    }

    .tech-category h4 {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--sfims-text);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .tech-category h4 i {
        color: #4ade80;
    }

    .tech-items {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .tech-item {
        background: var(--sfims-bg);
        border: 1px solid var(--sfims-border);
        padding: 10px 18px;
        border-radius: 25px;
        font-size: 0.9rem;
        color: var(--sfims-text);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tech-item:hover {
        background: #4ade80;
        color: #1e451e;
        border-color: #4ade80;
        transform: translateY(-2px);
    }

    .tech-item i {
        font-size: 1.1rem;
    }

    /* Timeline Section */
    .timeline-section {
        margin-top: 50px;
        position: relative;
    }

    .timeline-section::before {
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        width: 4px;
        height: 100%;
        background: linear-gradient(to bottom, #4ade80, #22c55e, #16a34a);
        border-radius: 2px;
    }

    .timeline-phase-label {
        text-align: center;
        margin: 30px 0 20px;
    }

    .phase-badge {
        display: inline-block;
        background: linear-gradient(135deg, #4ade80, #22c55e);
        color: #1e451e;
        padding: 10px 25px;
        border-radius: 30px;
        font-size: 0.9rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 15px rgba(74, 222, 128, 0.3);
    }

    .timeline-item {
        display: flex;
        justify-content: flex-end;
        padding-right: 50px;
        position: relative;
        margin-bottom: 40px;
    }

    .timeline-item:nth-child(even) {
        justify-content: flex-start;
        padding-right: 0;
        padding-left: 50px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        background: #4ade80;
        border: 4px solid var(--sfims-card-bg);
        border-radius: 50%;
        top: 0;
        right: -10px;
        z-index: 1;
        box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.2);
    }

    .timeline-item:nth-child(even)::before {
        right: auto;
        left: -10px;
    }

    .timeline-content {
        background: var(--sfims-card-bg);
        border: 1px solid var(--sfims-border);
        border-radius: 12px;
        padding: 20px 25px;
        max-width: 45%;
        position: relative;
    }

    .timeline-content::after {
        content: '';
        position: absolute;
        top: 10px;
        right: -12px;
        width: 0;
        height: 0;
        border-top: 10px solid transparent;
        border-bottom: 10px solid transparent;
        border-left: 12px solid var(--sfims-border);
    }

    .timeline-item:nth-child(even) .timeline-content::after {
        right: auto;
        left: -12px;
        border-left: none;
        border-right: 12px solid var(--sfims-border);
    }

    .timeline-date {
        font-size: 0.85rem;
        color: #4ade80;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .timeline-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--sfims-text);
        margin-bottom: 8px;
    }

    .timeline-desc {
        font-size: 0.9rem;
        color: var(--sfims-text);
        opacity: 0.8;
        line-height: 1.5;
    }

    /* Acknowledgment Section */
    .acknowledgment-section {
        background: var(--sfims-card-bg);
        border: 1px solid var(--sfims-border);
        border-radius: 16px;
        padding: 40px;
        margin-top: 50px;
    }

    .acknowledgment-section h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--sfims-text);
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .acknowledgment-section ul {
        list-style: none;
        padding: 0;
    }

    .acknowledgment-section li {
        padding: 15px 0;
        border-bottom: 1px solid var(--sfims-border);
        color: var(--sfims-text);
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .acknowledgment-section li:last-child {
        border-bottom: none;
    }

    .acknowledgment-section li i {
        color: #4ade80;
        font-size: 1.2rem;
        margin-top: 2px;
    }

    .ack-title {
        font-weight: 600;
        color: var(--sfims-text);
    }

    .ack-desc {
        font-size: 0.9rem;
        opacity: 0.75;
        margin-top: 4px;
    }

    /* License Section */
    .license-section {
        background: linear-gradient(135deg, rgba(74, 222, 128, 0.1), rgba(34, 197, 94, 0.05));
        border: 1px solid var(--sfims-border);
        border-radius: 16px;
        padding: 40px;
        margin-top: 50px;
        text-align: center;
    }

    .license-section h3 {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--sfims-text);
        margin-bottom: 15px;
    }

    .license-section p {
        color: var(--sfims-text);
        opacity: 0.8;
        line-height: 1.7;
        max-width: 700px;
        margin: 0 auto 20px;
    }

    .license-badges {
        display: flex;
        justify-content: center;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 20px;
    }

    .license-badge {
        background: var(--sfims-card-bg);
        border: 1px solid var(--sfims-border);
        padding: 8px 20px;
        border-radius: 25px;
        font-size: 0.85rem;
        color: var(--sfims-text);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .license-badge i {
        color: #4ade80;
    }

    /* Modal Styles */
    .team-modal {
        display: none;
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(8px);
    }

    .team-modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    }

    .team-modal-content {
        background: var(--sfims-card-bg);
        border-radius: 20px;
        padding: 35px;
        max-width: 550px;
        width: 90%;
        position: relative;
        animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid var(--sfims-border);
    }

    .team-modal-close {
        position: absolute;
        top: 15px;
        right: 18px;
        font-size: 1.8rem;
        cursor: pointer;
        color: var(--sfims-text);
        transition: all 0.3s ease;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .team-modal-close:hover {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.1);
    }

    .team-modal-photo {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        margin: 0 auto 20px;
        overflow: hidden;
        border: 4px solid #4ade80;
        box-shadow: 0 10px 30px rgba(74, 222, 128, 0.3);
    }

    .team-modal-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .team-modal-name {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--sfims-text);
        text-align: center;
        margin-bottom: 5px;
    }

    .team-modal-role {
        font-size: 1rem;
        color: #4ade80;
        text-align: center;
        margin-bottom: 8px;
        text-transform: uppercase;
        font-weight: 600;
    }

    .team-modal-contribution {
        font-size: 0.9rem;
        color: var(--sfims-text);
        text-align: center;
        opacity: 0.7;
        font-style: italic;
        margin-bottom: 20px;
    }

    .team-modal-bio {
        font-size: 0.95rem;
        color: var(--sfims-text);
        line-height: 1.7;
        margin-bottom: 20px;
        text-align: center;
    }

    .team-modal-skills {
        margin-bottom: 20px;
    }

    .team-modal-skills h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--sfims-text);
        margin-bottom: 12px;
        text-align: center;
    }

    .skill-tags {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 8px;
    }

    .skill-tag {
        background: var(--sfims-bg);
        color: var(--sfims-text);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85rem;
        border: 1px solid var(--sfims-border);
        transition: all 0.3s ease;
    }

    .skill-tag:hover {
        background: #4ade80;
        color: #1e451e;
        border-color: #4ade80;
    }

    .team-modal-social {
        display: flex;
        justify-content: center;
        gap: 15px;
        padding-top: 20px;
        border-top: 1px solid var(--sfims-border);
    }

    .team-modal-social a {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: var(--sfims-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--sfims-text);
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 1.2rem;
    }

    .team-modal-social a:hover {
        background: #4ade80;
        color: #1e451e;
        transform: translateY(-3px);
    }

    /* Search and Filter */
    .search-filter-container {
        display: flex;
        gap: 15px;
        margin-bottom: 40px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .search-box {
        flex: 1;
        min-width: 250px;
        max-width: 400px;
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 14px 20px 14px 48px;
        border: 1px solid var(--sfims-border);
        border-radius: 30px;
        background: var(--sfims-card-bg);
        color: var(--sfims-text);
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .search-box input:focus {
        outline: none;
        border-color: #4ade80;
        box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.15);
    }

    .search-box i {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--sfims-text);
        opacity: 0.5;
    }

    .filter-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 12px 22px;
        border: 1px solid var(--sfims-border);
        border-radius: 30px;
        background: var(--sfims-card-bg);
        color: var(--sfims-text);
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .filter-btn:hover,
    .filter-btn.active {
        background: #4ade80;
        color: #1e451e;
        border-color: #4ade80;
        transform: translateY(-2px);
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        .timeline-section::before {
            left: 20px;
        }
        
        .timeline-item,
        .timeline-item:nth-child(even) {
            padding-left: 60px;
            padding-right: 0;
            justify-content: flex-start;
        }
        
        .timeline-item::before,
        .timeline-item:nth-child(even)::before {
            left: 10px;
            right: auto;
        }
        
        .timeline-content,
        .timeline-item:nth-child(even) .timeline-content {
            max-width: 100%;
        }
        
        .timeline-content::after,
        .timeline-item:nth-child(even) .timeline-content::after {
            left: -12px;
            right: auto;
            border-left: none;
            border-right: 12px solid var(--sfims-border);
        }
    }

    @media (max-width: 768px) {
        .credits-hero h1 {
            font-size: 2.2rem;
        }

        .credits-hero p {
            font-size: 1rem;
        }

        .team-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .team-card {
            padding: 25px;
        }

        .team-photo {
            width: 120px;
            height: 120px;
        }

        .search-filter-container {
            flex-direction: column;
            align-items: stretch;
        }

        .search-box {
            max-width: 100%;
        }

        .filter-buttons {
            justify-content: center;
        }

        .tech-stack-section,
        .acknowledgment-section,
        .license-section {
            padding: 25px;
        }
    }

    /* Animation for cards */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(40px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(60px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .team-card {
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
    }

    .team-card:nth-child(1) { animation-delay: 0.1s; }
    .team-card:nth-child(2) { animation-delay: 0.2s; }
    .team-card:nth-child(3) { animation-delay: 0.3s; }
    .team-card:nth-child(4) { animation-delay: 0.4s; }
    .team-card:nth-child(5) { animation-delay: 0.5s; }
    .team-card:nth-child(6) { animation-delay: 0.6s; }

    /* Stats Section */
    .stats-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
        margin-bottom: 50px;
    }

    .stat-card {
        background: var(--sfims-card-bg);
        border: 1px solid var(--sfims-border);
        border-radius: 12px;
        padding: 25px 20px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        border-color: #4ade80;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        color: #4ade80;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 0.85rem;
        color: var(--sfims-text);
        opacity: 0.8;
    }

    /* Back to Top Button */
    .back-to-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 55px;
        height: 55px;
        border-radius: 50%;
        background: #4ade80;
        color: #1e451e;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 1000;
        box-shadow: 0 6px 20px rgba(74, 222, 128, 0.4);
        font-size: 1.3rem;
    }

    .back-to-top.visible {
        opacity: 1;
        visibility: visible;
    }

    .back-to-top:hover {
        transform: translateY(-8px);
        box-shadow: 0 10px 30px rgba(74, 222, 128, 0.5);
    }

    /* Tab Navigation for Sections */
    .credits-tabs {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-bottom: 40px;
        flex-wrap: wrap;
    }

    .credit-tab {
        padding: 12px 25px;
        border: 1px solid var(--sfims-border);
        border-radius: 30px;
        background: var(--sfims-card-bg);
        color: var(--sfims-text);
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .credit-tab:hover,
    .credit-tab.active {
        background: var(--sfims-green);
        color: #fff;
        border-color: var(--sfims-green);
    }

    .credit-tab.active i {
        color: #4ade80;
    }

    /* Section visibility */
    .credits-section {
        display: none;
    }

    .credits-section.active {
        display: block;
        animation: fadeIn 0.5s ease;
    }
</style>

<div class="container-fluid">
    <!-- Hero Section -->
    <div class="credits-hero">
        <h1><i class="bi bi-people-fill me-3"></i>Meet Our Team</h1>
        <p>The dedicated professionals behind the Supply and Facilities Inventory Management System (SFIMS) at Pamantasan ng Lungsod ng Muntinlupa</p>
        <div class="credits-badge">
            <i class="bi bi-award-fill me-2"></i>
            Version 2.5 | Since 2025
        </div>
    </div>

    <!-- Stats Section -->
    <div class="stats-section">
        <div class="stat-card">
            <div class="stat-number" data-count="3">0</div>
            <div class="stat-label">Team Members</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" data-count="7">0</div>
            <div class="stat-label">Months Development</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" data-count="15">0</div>
            <div class="stat-label">Modules Built</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" data-count="50">0</div>
            <div class="stat-label">Features</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" data-count="1000">0</div>
            <div class="stat-label">Hours Invested</div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="credits-tabs">
        <button class="credit-tab active" data-tab="team">
            <i class="bi bi-people"></i> Team
        </button>
        <button class="credit-tab" data-tab="techstack">
            <i class="bi bi-code-slash"></i> Tech Stack
        </button>
        <button class="credit-tab" data-tab="timeline">
            <i class="bi bi-clock-history"></i> Timeline
        </button>
        <button class="credit-tab" data-tab="acknowledgments">
            <i class="bi bi-heart"></i> Thanks To
        </button>
    </div>

    <!-- Team Section -->
    <div class="credits-section active" id="team-section">
        <div class="section-title">
            <h2>Development Team</h2>
            <p>The talented individuals who brought this system to life</p>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter-container">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="teamSearch" placeholder="Search team members...">
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="development">Development</button>
                <button class="filter-btn" data-filter="design">Design</button>
                <button class="filter-btn" data-filter="documentation">Documentation</button>
                <button class="filter-btn" data-filter="management">Management</button>
            </div>
        </div>

        <div class="team-grid" id="teamGrid">
            <!-- Team Member 1 -->
            <div class="team-card" data-category="management development" data-name="Jaifi Niel D. Abuan" data-role="Project Manager, Lead Developer & Backend Specialist" data-contribution="System architecture, database design, core functionality" data-bio="The visionary leader who conceptualized and led the development of the SFIMS, ensuring alignment with organizational goals and user needs. Expert in PHP, MySQL, and system architecture with 5+ years of experience in educational institution systems." data-skills="PHP, MySQL, Project Management, System Architecture, JavaScript, Bootstrap, REST API Design, Database Optimization">
                <div class="team-photo">
                    <img src="<?php echo BASE_URL; ?>assets/img/team/jnplmun.jpg" alt="Jaifi Niel D. Abuan" onerror="this.src='https://ui-avatars.com/api/?name=Jaifi+Niel+D.+Abuan&size=150&background=4ade80&color=1e451e&bold=true'">
                </div>
                <h3 class="team-name">Jaifi Niel D. Abuan</h3>
                <p class="team-role">Project Manager, Lead Developer</p>
                <p class="team-contribution">System architecture, database design</p>
                <p class="team-bio">The visionary leader who conceptualized and led the development of the SFIMS, ensuring alignment with organizational goals and user needs.</p>
                <div class="team-social">
                    <a href="mailto:abuanjaifiniel_bscs@plmun.edu.ph" title="Email"><i class="bi bi-envelope-fill"></i></a>
                </div>
            </div>

            <!-- Team Member 2 -->
            <div class="team-card" data-category="design development" data-name="Benjamin Arado" data-role="Frontend Developer & UI/UX Designer" data-contribution="User interface design, dashboard widgets, responsive layout" data-bio="Specializes in creating responsive and user-friendly interfaces. Implemented the dark mode theme and dashboard widgets. Passionate about creating intuitive user experiences that enhance productivity." data-skills="HTML, CSS, JavaScript, Bootstrap 5, UI/UX Design, Responsive Design, jQuery, Theme Development, Animation">
                <div class="team-photo">
                    <img src="<?php echo BASE_URL; ?>assets/img/team/aradoplmun.jpg" alt="Benjamin Arado" onerror="this.src='https://ui-avatars.com/api/?name=Benjamin+Arado&size=150&background=4ade80&color=1e451e&bold=true'">
                </div>
                <h3 class="team-name">Benjamin Arado</h3>
                <p class="team-role">Frontend Developer</p>
                <p class="team-contribution">UI/UX design, responsive layout</p>
                <p class="team-bio">Specializes in creating responsive and user-friendly interfaces. Implemented the dark mode theme and dashboard widgets.</p>
                <div class="team-social">
                    <a href="mailto:aradobenjamin_bscs@plmun.edu.ph" title="Email"><i class="bi bi-envelope-fill"></i></a>
                </div>
            </div>

            <!-- Team Member 3 -->
            <div class="team-card" data-category="documentation" data-name="Allysa Mae Lopena" data-role="Documentation Specialist & QA Tester" data-contribution="User guides, technical documentation, testing" data-bio="Responsible for creating comprehensive documentation for the SFIMS, including user guides and technical specifications. Ensured quality through thorough testing and user feedback integration." data-skills="Technical Writing, Documentation, User Guides, API Documentation, Quality Assurance, Testing, User Training">
                <div class="team-photo">
                    <img src="<?php echo BASE_URL; ?>assets/img/team/lopenaplmun.jpg" alt="Allysa Mae Lopena" onerror="this.src='https://ui-avatars.com/api/?name=Allysa+Mae+Lopena&size=150&background=4ade80&color=1e451e&bold=true'">
                </div>
                <h3 class="team-name">Allysa Mae Lopena</h3>
                <p class="team-role">Documentation Specialist</p>
                <p class="team-contribution">User guides, technical documentation</p>
                <p class="team-bio">Responsible for creating comprehensive documentation for the SFIMS, including user guides and technical specifications.</p>
                <div class="team-social">
                    <a href="mailto:lopenaallysamae_bscs@plmun.edu.ph" title="Email"><i class="bi bi-envelope-fill"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tech Stack Section -->
    <div class="credits-section" id="techstack-section">
        <div class="section-title">
            <h2>Technology Stack</h2>
            <p>The powerful tools and technologies that power SFIMS</p>
        </div>

        <div class="tech-stack-section">
            <div class="row">
                <div class="col-lg-6">
                    <div class="tech-category">
                        <h4><i class="bi bi-hdd-stack"></i> Backend</h4>
                        <div class="tech-items">
                            <span class="tech-item"><i class="bi bi-filetype-php"></i> PHP 8.x</span>
                            <span class="tech-item"><i class="bi bi-database"></i> MySQL</span>
                            <span class="tech-item"><i class="bi bi-braces"></i> REST API</span>
                            <span class="tech-item"><i class="bi bi-shield-check"></i> Security</span>
                        </div>
                    </div>

                    <div class="tech-category">
                        <h4><i class="bi bi-palette"></i> Frontend</h4>
                        <div class="tech-items">
                            <span class="tech-item"><i class="bi bi-filetype-html"></i> HTML5</span>
                            <span class="tech-item"><i class="bi bi-filetype-css"></i> CSS3</span>
                            <span class="tech-item"><i class="bi bi-filetype-js"></i> JavaScript</span>
                            <span class="tech-item"><i class="bi bi-bootstrap"></i> Bootstrap 5</span>
                            <span class="tech-item"><i class="bi bi-jquery"></i> jQuery</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="tech-category">
                        <h4><i class="bi bi-tools"></i> Development Tools</h4>
                        <div class="tech-items">
                            <span class="tech-item"><i class="bi bi-code-square"></i> VS Code</span>
                            <span class="tech-item"><i class="bi bi-git"></i> Git</span>
                            <span class="tech-item"><i class="bi bi-terminal"></i> XAMPP</span>
                            <span class="tech-item"><i class="bi bi-bug"></i> Debugging</span>
                        </div>
                    </div>

                    <div class="tech-category">
                        <h4><i class="bi bi-cloud"></i> External Services</h4>
                        <div class="tech-items">
                            <span class="tech-item"><i class="bi bi-fonts"></i> Google Fonts</span>
                            <span class="tech-item"><i class="bi bi-bootstrap-icons"></i> Bootstrap Icons</span>
                            <span class="tech-item"><i class="bi bi-upc"></i> Barcode API</span>
                            <span class="tech-item"><i class="bi bi-envelope"></i> Email Services</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline Section -->
    <div class="credits-section" id="timeline-section">
        <div class="section-title">
            <h2>Project Journey</h2>
            <p>Key milestones in the development of SFIMS</p>
        </div>

        <div class="timeline-section">
            <!-- Analysis Phase -->
            <div class="timeline-phase-label">
                <span class="phase-badge">Analysis Phase</span>
            </div>
            
            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Aug 15 - Sep 5, 2025</div>
                    <h4 class="timeline-title">Research Gathering</h4>
                    <p class="timeline-desc">Gathering requirements, understanding current inventory processes, and identifying pain points in existing systems.</p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Sep 6 - Sep 21, 2025</div>
                    <h4 class="timeline-title">System Analysis</h4>
                    <p class="timeline-desc">Analyzing workflow requirements, user roles, and defining system scope. Created comprehensive requirement documents.</p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Sep 22 - Sep 28, 2025</div>
                    <h4 class="timeline-title">Problem Statement</h4>
                    <p class="timeline-desc">Defined project objectives, constraints, and success criteria. Documented expected deliverables and timeline.</p>
                </div>
            </div>

            <!-- Planning Phase -->
            <div class="timeline-phase-label">
                <span class="phase-badge">Planning Phase</span>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Oct 12 - Oct 19, 2025</div>
                    <h4 class="timeline-title">Creating New System</h4>
                    <p class="timeline-desc">Designed system architecture and selected appropriate technologies for the new inventory management solution.</p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Oct 20 - Oct 26, 2025</div>
                    <h4 class="timeline-title">Identifying System</h4>
                    <p class="timeline-desc">Identified core modules, data structures, and system boundaries. Defined integration points with existing infrastructure.</p>
                </div>
            </div>

            <!-- Design Phase -->
            <div class="timeline-phase-label">
                <span class="phase-badge">Design Phase</span>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Oct 27 - Nov 9, 2025</div>
                    <h4 class="timeline-title">Create Prototype</h4>
                    <p class="timeline-desc">Built functional prototype with core UI components. Conducted initial user testing and gathered feedback.</p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Nov 10 - Nov 12, 2025</div>
                    <h4 class="timeline-title">ERD Development</h4>
                    <p class="timeline-desc">Created Entity-Relationship Diagram defining database tables, relationships, and constraints.</p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Nov 13 - Nov 30, 2025</div>
                    <h4 class="timeline-title">UI Layout Design</h4>
                    <p class="timeline-desc">Designed user interface layouts, created wireframes, and established design system with dark/light themes.</p>
                </div>
            </div>

            <!-- Development Phase -->
            <div class="timeline-phase-label">
                <span class="phase-badge">Development Phase</span>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Dec 1 - Dec 10, 2025</div>
                    <h4 class="timeline-title">Integrate Database</h4>
                    <p class="timeline-desc">Set up MySQL database, created tables, implemented relationships, and optimized queries for performance.</p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Jan 5 - Mar 10, 2026</div>
                    <h4 class="timeline-title">Code System Modules</h4>
                    <p class="timeline-desc">Developed all core modules including inventory management, transactions, reporting, and user authentication.</p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Mar 11 - Mar 20, 2026</div>
                    <h4 class="timeline-title">Testing & QA</h4>
                    <p class="timeline-desc">Comprehensive testing including unit tests, integration tests, and user acceptance testing. Bug fixes and optimizations.</p>
                </div>
            </div>

            <!-- Maintenance Phase -->
            <div class="timeline-phase-label">
                <span class="phase-badge">Maintenance</span>
            </div>

            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">Mar 21, 2026 - Present</div>
                    <h4 class="timeline-title">Deployment & Maintenance</h4>
                    <p class="timeline-desc">System deployed and live at Pamantasan ng Lungsod ng Muntinlupa. Ongoing maintenance, updates, and user support.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Acknowledgments Section -->
    <div class="credits-section" id="acknowledgments-section">
        <div class="section-title">
            <h2>Special Thanks</h2>
            <p>To all those who made this project possible</p>
        </div>

        <div class="acknowledgment-section">
            <h3><i class="bi bi-heart-fill text-danger"></i> Heartfelt Acknowledgments</h3>
            <ul>
                <li>
                    <i class="bi bi-building"></i>
                    <div>
                        <div class="ack-title">Pamantasan ng Lungsod ng Muntinlupa</div>
                        <div class="ack-desc">For providing the opportunity, resources, and institutional support to develop this system</div>
                    </div>
                </li>
                <li>
                    <i class="bi bi-box-seam"></i>
                    <div>
                        <div class="ack-title">Supply and Property Management Office</div>
                        <div class="ack-desc">For their invaluable input, requirements gathering, and continuous feedback throughout development</div>
                    </div>
                </li>
                <li>
                    <i class="bi bi-people"></i>
                    <div>
                        <div class="ack-title">All SFIMS Users</div>
                        <div class="ack-desc">For their feedback, patience, and valuable suggestions during the development and testing phases</div>
                    </div>
                </li>
                <li>
                    <i class="bi bi-code-slash"></i>
                    <div>
                        <div class="ack-title">Open Source Community</div>
                        <div class="ack-desc">For the amazing tools, libraries, and frameworks that made this project possible</div>
                    </div>
                </li>
                <li>
                    <i class="bi bi-mortarboard"></i>
                    <div>
                        <div class="ack-title">Academic Advisors</div>
                        <div class="ack-desc">For guidance, mentorship, and constructive criticism that shaped the project's direction</div>
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <!-- License Section -->
    <div class="license-section">
        <h3><i class="bi bi-file-earmark-text me-2"></i>License & Copyright</h3>
        <p>
            SFIMS (Supply and Facilities Inventory Management System) is developed and maintained by the PLMUN Development Team. 
            This software is proprietary and intended for internal use at Pamantasan ng Lungsod ng Muntinlupa.
        </p>
        <p>
            © <?php echo date('Y'); ?> Pamantasan ng Lungsod ng Muntinlupa. All rights reserved.
        </p>
        <div class="license-badges">
            <span class="license-badge"><i class="bi bi-shield-lock"></i> Proprietary</span>
            <span class="license-badge"><i class="bi bi-person-badge"></i> Internal Use Only</span>
            <span class="license-badge"><i class="bi bi.Version"></i> v2.5</span>
        </div>
    </div>

    <!-- Footer Note -->
    <div class="text-center mt-5 mb-4">
        <p class="text-muted">
            <i class="bi bi-info-circle me-2"></i>
            SFIMS Version 2.5 | Last Updated: <?php echo date('F Y'); ?>
        </p>
        <p class="text-muted small">
            For inquiries or support, please contact the development team through the official channels.
        </p>
    </div>
</div>

<!-- Team Member Modal -->
<div class="team-modal" id="teamModal">
    <div class="team-modal-content">
        <span class="team-modal-close" id="modalClose">&times;</span>
        <div class="team-modal-photo">
            <img id="modalPhoto" src="" alt="">
        </div>
        <h3 class="team-modal-name" id="modalName"></h3>
        <p class="team-modal-role" id="modalRole"></p>
        <p class="team-modal-contribution" id="modalContribution"></p>
        <p class="team-modal-bio" id="modalBio"></p>
        <div class="team-modal-skills">
            <h4>Skills & Expertise</h4>
            <div class="skill-tags" id="modalSkills"></div>
        </div>
        <div class="team-modal-social">
            <a href="#" title="Email"><i class="bi bi-envelope-fill"></i></a>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<div class="back-to-top" id="backToTop">
    <i class="bi bi-arrow-up"></i>
</div>

<script>
(function() {
    'use strict';
    
    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCreditsPage);
    } else {
        initCreditsPage();
    }
    
    function initCreditsPage() {
        try {
            // Tab Navigation
            const creditTabs = document.querySelectorAll('.credit-tab');
            const creditSections = document.querySelectorAll('.credits-section');
            
            creditTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Update active tab
                    creditTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show corresponding section
                    creditSections.forEach(section => {
                        section.classList.remove('active');
                        if (section.id === tabId + '-section') {
                            section.classList.add('active');
                        }
                    });
                    
                    // Reinitialize stats animation when switching to team tab
                    if (tabId === 'team') {
                        initStatsCounter();
                    }
                });
            });

            // Team data
            var teamData = [];
            var cards = document.querySelectorAll('.team-card');
            cards.forEach(function(card) {
                teamData.push({
                    element: card,
                    name: card.getAttribute('data-name') || '',
                    role: card.getAttribute('data-role') || '',
                    contribution: card.getAttribute('data-contribution') || '',
                    bio: card.getAttribute('data-bio') || '',
                    skills: card.getAttribute('data-skills') || '',
                    category: card.getAttribute('data-category') || '',
                    photo: card.querySelector('.team-photo img') ? card.querySelector('.team-photo img').src : ''
                });
            });

            // Search functionality
            var searchInput = document.getElementById('teamSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    var searchTerm = this.value.toLowerCase();
                    filterTeam(searchTerm, getActiveFilter());
                });
            }

            // Filter functionality
            var filterButtons = document.querySelectorAll('.filter-btn');
            filterButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    filterButtons.forEach(function(b) {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');
                    var filter = this.getAttribute('data-filter');
                    var searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
                    filterTeam(searchTerm, filter);
                });
            });

            function getActiveFilter() {
                var activeBtn = document.querySelector('.filter-btn.active');
                return activeBtn ? activeBtn.getAttribute('data-filter') : 'all';
            }

            function filterTeam(searchTerm, filter) {
                teamData.forEach(function(member) {
                    var matchesSearch = member.name.toLowerCase().indexOf(searchTerm) !== -1 ||
                                       member.role.toLowerCase().indexOf(searchTerm) !== -1 ||
                                       member.bio.toLowerCase().indexOf(searchTerm) !== -1;
                    var matchesFilter = filter === 'all' || member.category.indexOf(filter) !== -1;
                    
                    if (matchesSearch && matchesFilter) {
                        member.element.style.display = 'block';
                        member.element.style.animation = 'fadeInUp 0.5s ease forwards';
                    } else {
                        member.element.style.display = 'none';
                    }
                });
            }

            // Modal functionality
            var modal = document.getElementById('teamModal');
            var modalClose = document.getElementById('modalClose');

            cards.forEach(function(card) {
                card.addEventListener('click', function() {
                    try {
                        var name = this.getAttribute('data-name') || '';
                        var role = this.getAttribute('data-role') || '';
                        var contribution = this.getAttribute('data-contribution') || '';
                        var bio = this.getAttribute('data-bio') || '';
                        var skills = this.getAttribute('data-skills') || '';
                        var photo = this.querySelector('.team-photo img') ? this.querySelector('.team-photo img').src : '';

                        var modalName = document.getElementById('modalName');
                        var modalRole = document.getElementById('modalRole');
                        var modalContribution = document.getElementById('modalContribution');
                        var modalBio = document.getElementById('modalBio');
                        var modalPhoto = document.getElementById('modalPhoto');
                        var modalSkills = document.getElementById('modalSkills');

                        if (modalName) modalName.textContent = name;
                        if (modalRole) modalRole.textContent = role;
                        if (modalContribution) modalContribution.textContent = contribution;
                        if (modalBio) modalBio.textContent = bio;
                        if (modalPhoto) {
                            modalPhoto.src = photo;
                            modalPhoto.alt = name;
                        }

                        // Populate skills
                        if (modalSkills) {
                            modalSkills.innerHTML = '';
                            if (skills) {
                                skills.split(', ').forEach(function(skill) {
                                    var skillTag = document.createElement('span');
                                    skillTag.className = 'skill-tag';
                                    skillTag.textContent = skill;
                                    modalSkills.appendChild(skillTag);
                                });
                            }
                        }

                        if (modal) modal.classList.add('show');
                    } catch (e) {
                        console.error('Error opening modal:', e);
                    }
                });
            });

            if (modalClose) {
                modalClose.addEventListener('click', function() {
                    if (modal) modal.classList.remove('show');
                });
            }

            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.classList.remove('show');
                    }
                });
            }

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal && modal.classList.contains('show')) {
                    modal.classList.remove('show');
                }
            });

            // Stats counter animation
            function initStatsCounter() {
                var statNumbers = document.querySelectorAll('.stat-number');
                
                function animateCounter(element, target) {
                    var current = 0;
                    var increment = Math.max(1, Math.floor(target / 50));
                    var timer = setInterval(function() {
                        current += increment;
                        if (current >= target) {
                            element.textContent = target;
                            clearInterval(timer);
                        } else {
                            element.textContent = Math.floor(current);
                        }
                    }, 30);
                }

                // Use IntersectionObserver if available, otherwise just animate
                if ('IntersectionObserver' in window) {
                    var observerOptions = {
                        threshold: 0.5
                    };

                    var observer = new IntersectionObserver(function(entries) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                var target = entry.target;
                                var count = parseInt(target.getAttribute('data-count')) || 0;
                                animateCounter(target, count);
                                observer.unobserve(target);
                            }
                        });
                    }, observerOptions);

                    statNumbers.forEach(function(stat) {
                        observer.observe(stat);
                    });
                } else {
                    // Fallback for browsers without IntersectionObserver
                    statNumbers.forEach(function(stat) {
                        var count = parseInt(stat.getAttribute('data-count')) || 0;
                        animateCounter(stat, count);
                    });
                }
            }

            // Initialize stats on page load
            initStatsCounter();

            // Back to top button
            var backToTop = document.getElementById('backToTop');
            
            if (backToTop) {
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 300) {
                        backToTop.classList.add('visible');
                    } else {
                        backToTop.classList.remove('visible');
                    }
                });

                backToTop.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

            // Keyboard navigation for cards
            cards.forEach(function(card, index) {
                card.setAttribute('tabindex', '0');
                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                    if (e.key === 'ArrowRight' && index < cards.length - 1) {
                        cards[index + 1].focus();
                    }
                    if (e.key === 'ArrowLeft' && index > 0) {
                        cards[index - 1].focus();
                    }
                });
            });

            // Print functionality
            window.printCredits = function() {
                window.print();
            };

            // Share functionality
            window.shareCredits = function() {
                if (navigator.share) {
                    navigator.share({
                        title: 'SFIMS Development Team',
                        text: 'Meet the team behind the Supply and Facilities Inventory Management System',
                        url: window.location.href
                    });
                } else {
                    // Fallback: copy to clipboard
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(window.location.href).then(function() {
                            alert('Link copied to clipboard!');
                        });
                    } else {
                        alert('Share this page: ' + window.location.href);
                    }
                }
            };

            // Add print and share buttons dynamically
            var heroSection = document.querySelector('.credits-hero');
            if (heroSection) {
                var actionButtons = document.createElement('div');
                actionButtons.style.cssText = 'position: absolute; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 2;';
                actionButtons.innerHTML = '<button onclick="printCredits()" class="btn btn-sm btn-light" title="Print" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white;"><i class="bi bi-printer"></i></button>' +
                                         '<button onclick="shareCredits()" class="btn btn-sm btn-light" title="Share" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white;"><i class="bi bi-share"></i></button>';
                heroSection.style.position = 'relative';
                heroSection.appendChild(actionButtons);
            }
            
        } catch (error) {
            console.error('Error initializing credits page:', error);
        }
    }
})();
</script>

<?php require_once 'partials/footer.php'; ?>
