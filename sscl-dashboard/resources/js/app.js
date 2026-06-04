import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('ui', () => ({
  mobileOpen: false,
  theme: localStorage.getItem('theme') || 'light',

  init() {
    this.applyTheme(this.theme);
  },

  applyTheme(theme) {
    this.theme = theme;
    localStorage.setItem('theme', theme);
    document.documentElement.classList.toggle('dark', theme === 'dark');
  },

  toggleTheme() {
    this.applyTheme(this.theme === 'dark' ? 'light' : 'dark');
  },
}));

Alpine.data('backupConsole', (config = {}) => ({
  endpoint: config.endpoint,
  token: config.token,
  format: config.initialFormat || 'zip',
  busy: false,
  progress: 0,
  status: '',
  error: '',
  latest: null,
  timer: null,

  start() {
    if (this.busy) return;

    this.busy = true;
    this.error = '';
    this.latest = null;
    this.progress = 8;
    this.status = 'Menyiapkan dump database';

    this.timer = window.setInterval(() => {
      if (this.progress < 92) {
        this.progress += Math.max(1, Math.round((92 - this.progress) * 0.18));
      }

      if (this.progress >= 35 && this.progress < 70) {
        this.status = 'Menulis data ke file backup';
      } else if (this.progress >= 70) {
        this.status = this.format === 'zip' ? 'Mengemas file ZIP' : 'Merapikan file SQL';
      }
    }, 260);

    fetch(this.endpoint, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.token,
      },
      body: JSON.stringify({ format: this.format }),
    })
      .then(async (response) => {
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
          throw new Error(payload.message || 'Backup database gagal diproses.');
        }

        return payload;
      })
      .then((payload) => {
        this.latest = payload.backup;
        this.progress = 100;
        this.status = 'Backup selesai';
      })
      .catch((error) => {
        this.error = error.message || 'Backup database gagal diproses.';
        this.progress = 0;
        this.status = 'Backup gagal';
      })
      .finally(() => {
        window.clearInterval(this.timer);
        this.timer = null;
        window.setTimeout(() => {
          this.busy = false;
        }, 500);
      });
  },
}));

Alpine.start();
