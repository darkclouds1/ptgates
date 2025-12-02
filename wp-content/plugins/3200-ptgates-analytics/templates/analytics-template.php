<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div id="ptg-analytics-app" class="ptg-analytics-container">
    <div class="ptg-loading">
        <div class="ptg-spinner"></div>
        <span>성적 분석 데이터를 불러오는 중입니다...</span>
    </div>
</div>

<style>
:root {
    --ptg-primary: #4A90E2;
    --ptg-secondary: #50E3C2;
    --ptg-danger: #FF6B6B;
    --ptg-warning: #FFD166;
    --ptg-dark: #2C3E50;
    --ptg-light: #F8F9FA;
    --ptg-card-bg: #FFFFFF;
    --ptg-shadow: 0 4px 6px rgba(0,0,0,0.05);
    --ptg-radius: 12px;
}

.ptg-analytics-container {
    max-width: 1000px;
    margin: 40px auto;
    font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, system-ui, Roboto, sans-serif;
    color: var(--ptg-dark);
}

.ptg-loading {
    text-align: center;
    padding: 60px;
    color: #888;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
}

.ptg-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--ptg-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.ptg-dashboard {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

/* Stats Grid */
.ptg-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.ptg-stat-card {
    background: var(--ptg-card-bg);
    padding: 25px;
    border-radius: var(--ptg-radius);
    box-shadow: var(--ptg-shadow);
    text-align: center;
    transition: transform 0.2s;
}

.ptg-stat-card:hover {
    transform: translateY(-5px);
}

.ptg-stat-card h3 {
    margin: 0 0 10px;
    font-size: 0.95rem;
    color: #888;
    font-weight: 500;
}

.ptg-stat-value {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--ptg-dark);
}

.ptg-stat-sub {
    font-size: 0.85rem;
    color: #aaa;
    margin-top: 5px;
}

/* Charts Section */
.ptg-charts-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 768px) {
    .ptg-charts-row {
        grid-template-columns: 1fr;
    }
}

.ptg-section {
    background: var(--ptg-card-bg);
    padding: 25px;
    border-radius: var(--ptg-radius);
    box-shadow: var(--ptg-shadow);
}

.ptg-section h3 {
    margin: 0 0 25px;
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--ptg-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.ptg-section h3::before {
    content: '';
    display: block;
    width: 6px;
    height: 24px;
    background: var(--ptg-primary);
    border-radius: 3px;
}

/* Subject Grid & Grouping */
.ptg-subject-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
}

@media (max-width: 768px) {
    .ptg-subject-grid {
        grid-template-columns: 1fr;
    }
}

.ptg-subject-card {
    background: #ffffff;
    border: 1px solid #cbd5e1; /* Slate-300: Clear border */
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    overflow: hidden; /* Ensure header respects radius */
    display: flex;
    flex-direction: column;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.ptg-subject-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    border-color: var(--ptg-primary);
}

/* Remove the gradient accent to focus on header distinction */
.ptg-subject-card::before {
    display: none;
}

.ptg-group-title {
    background: #f1f5f9; /* Slate-100: Distinct header background */
    color: #334155; /* Slate-700 */
    font-size: 1rem;
    font-weight: 700;
    padding: 16px 20px;
    margin: 0;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Compact List */
.ptg-weak-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ptg-compact-list {
    padding: 20px; /* Padding for content */
}

.ptg-compact-list li {
    display: flex;
    align-items: center;
    margin-bottom: 8px; /* Reduced margin */
    font-size: 0.9rem; /* Slightly smaller font */
    border-bottom: 1px solid #f9f9f9;
    padding-bottom: 4px;
}

.ptg-subject-name {
    width: 140px; 
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex-shrink: 0; 
}

.ptg-progress-bar {
    flex-grow: 1;
    height: 8px; /* Thinner bar */
    background: #edf2f7;
    border-radius: 4px;
    margin: 0 10px;
    overflow: hidden;
    position: relative;
}

.ptg-progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 1s ease-in-out;
}

/* Accuracy Colors */
.ptg-excellent { background-color: #2ECC71; } /* Green */
.ptg-average { background-color: #F39C12; } /* Orange */
.ptg-poor { background-color: #E74C3C; } /* Red */

.ptg-excellent-text { color: #2ECC71; }
.ptg-average-text { color: #F39C12; }
.ptg-poor-text { color: #E74C3C; }

.ptg-accuracy-group {
    text-align: right;
    min-width: 140px; 
}

.ptg-accuracy {
    font-weight: 700;
    /* Color handled by class */
}

.ptg-counts {
    font-size: 0.75rem;
    color: #999;
    display: block;
}

.ptg-legend {
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 15px;
    text-align: right;
    font-style: italic;
}

/* Canvas Containers */
.ptg-chart-container {
    position: relative;
    height: 250px;
    width: 100%;
}
/* Color-coded Borders */
.ptg-subject-card.ptg-border-foundation {
    border: 2px solid #10b981 !important; /* Emerald-500 */
}
.ptg-subject-card.ptg-border-foundation .ptg-group-title {
    background: #ecfdf5 !important; /* Emerald-50 */
    color: #065f46 !important; /* Emerald-800 */
    border-bottom-color: #d1fae5 !important;
}

.ptg-subject-card.ptg-border-assessment {
    border: 2px solid #3b82f6 !important; /* Blue-500 */
}
.ptg-subject-card.ptg-border-assessment .ptg-group-title {
    background: #eff6ff !important; /* Blue-50 */
    color: #1e40af !important; /* Blue-800 */
    border-bottom-color: #dbeafe !important;
}

.ptg-subject-card.ptg-border-intervention {
    border: 2px solid #8b5cf6 !important; /* Violet-500 */
}
.ptg-subject-card.ptg-border-intervention .ptg-group-title {
    background: #f5f3ff !important; /* Violet-50 */
    color: #5b21b6 !important; /* Violet-800 */
    border-bottom-color: #ede9fe !important;
}

.ptg-subject-card.ptg-border-law {
    border: 2px solid #f59e0b !important; /* Amber-500 */
}
.ptg-subject-card.ptg-border-law .ptg-group-title {
    background: #fffbeb !important; /* Amber-50 */
    color: #92400e !important; /* Amber-800 */
    border-bottom-color: #fde68a !important;
}
</style>


