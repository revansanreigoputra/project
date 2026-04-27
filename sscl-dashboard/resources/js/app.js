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

Alpine.start();