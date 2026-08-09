<style>
    body {
        overflow-x: hidden;
        min-height: 100vh;
        margin: 0;
        background: linear-gradient(180deg, #f4f7f5 0%, #eef3f0 100%);
        padding-top: 0;
    }

    .panel-shell {
        display: grid;
        grid-template-rows: 4.25rem 1fr;
        height: 100vh;
        width: 100%;
        margin: 0;
    }

    .app-header {
        position: relative;
        z-index: 40;
        background: linear-gradient(135deg, #00473d 0%, #00594e 100%);
        color: white;
        box-shadow: 0 10px 30px rgba(0, 89, 78, 0.22);
        border: 0;
        margin: 0;
        top: 0;
    }

    .profile-menu {
        position: absolute;
        top: calc(100% + 0.75rem);
        right: 0;
        width: 18rem;
        background: white;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 1rem;
        box-shadow: 0 24px 50px rgba(15, 23, 42, 0.18);
        display: none;
    }

    .profile-menu.open {
        display: block;
    }

    .sidebar-link.active {
        background: #e6f2f0;
        color: #00594E;
        font-weight: 700;
        border-left: 4px solid #B5A160;
    }

    .evaluado-tab-btn,
    .evaluador-tab-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.55rem 1.1rem;
        border-radius: 0.75rem;
        font-size: 0.78rem;
        font-weight: 700;
        color: #64748b;
        background: #f1f5f9;
        border: 1px solid transparent;
        transition: all 0.15s ease;
        cursor: pointer;
        white-space: nowrap;
    }

    .evaluado-tab-btn.hidden,
    .evaluador-tab-btn.hidden {
        display: none;
    }

    .evaluado-tab-btn:hover,
    .evaluador-tab-btn:hover {
        color: #00594E;
        background: #e6f2f0;
    }

    .evaluado-tab-btn.active,
    .evaluador-tab-btn.active {
        background: #00594E;
        color: white;
        box-shadow: 0 4px 12px rgba(0, 89, 78, 0.25);
    }

    .evaluado-tab-panel,
    .evaluador-tab-panel {
        animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .panel-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
    }
</style>
