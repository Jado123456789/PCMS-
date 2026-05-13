<style>
  .admin-hero {
    border: 0;
    border-radius: 1rem;
    background: linear-gradient(135deg, #102a43 0%, #1d4f73 55%, #2f80a7 100%);
    overflow: hidden;
    position: relative;
  }

  .admin-hero::after {
    content: "";
    position: absolute;
    right: -90px;
    bottom: -130px;
    width: 280px;
    height: 280px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.09);
  }

  .admin-stat-card,
  .admin-panel {
    border: 1px solid rgba(15, 23, 42, 0.06);
    border-radius: 1rem;
    box-shadow: 0 0.75rem 1.5rem rgba(15, 23, 42, 0.04);
  }

  .admin-stat-card {
    height: 100%;
  }

  .admin-stat-icon {
    width: 48px;
    height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    font-size: 1rem;
  }

  .admin-chart {
    min-height: 290px;
  }

  .admin-table th {
    white-space: nowrap;
  }

  .admin-table td {
    vertical-align: middle;
  }
</style>
