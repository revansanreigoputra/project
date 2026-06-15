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

Alpine.data('confirmDialog', () => ({
  isOpen: false,
  dialog: {
    title: '',
    message: '',
    subject: '',
    action: '',
    method: 'POST',
    confirmLabel: 'Konfirmasi',
    cancelLabel: 'Batal',
    icon: 'alert',
    tone: 'danger',
  },

  open(config = {}) {
    this.dialog = {
      ...this.dialog,
      title: config.title || 'Konfirmasi Aksi',
      message: config.message || 'Apakah Anda yakin ingin melanjutkan?',
      subject: config.subject || '',
      action: config.action || '',
      method: (config.method || 'POST').toUpperCase(),
      confirmLabel: config.confirmLabel || 'Konfirmasi',
      cancelLabel: config.cancelLabel || 'Batal',
      icon: config.icon || 'alert',
      tone: config.tone || 'danger',
    };
    this.isOpen = true;
    this.$nextTick(() => this.$refs.cancelButton?.focus());
  },

  close() {
    this.isOpen = false;
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

Alpine.data('scanUploadPage', (config = {}) => ({
  fileName: '',
  uploadId: null,
  uploadUrl: config.uploadUrl,
  csrfToken: config.csrfToken,
  errorMessage: '',
  uploadMessage: '',
  isDragging: false,
  isUploading: false,
  isProcessing: false,
  results: Array.isArray(config.results) ? config.results : [],
  resultSearch: '',
  resultLabel: 'all',
  resultSort: 'row',
  sortDirection: 'asc',
  perPage: 10,
  page: 1,
  rowDetailOpen: false,
  rowDetail: { probabilities: [] },

  get filteredRows() {
    const search = this.resultSearch.trim().toLowerCase();
    const rows = this.results.filter((row) => {
      const labelMatches = this.resultLabel === 'all' || row.label === this.resultLabel;
      const searchMatches = !search || String(row.search || '').toLowerCase().includes(search);

      return labelMatches && searchMatches;
    });

    return rows.sort((left, right) => {
      const leftValue = this.sortValue(left);
      const rightValue = this.sortValue(right);
      const comparison = typeof leftValue === 'number' && typeof rightValue === 'number'
        ? leftValue - rightValue
        : String(leftValue).localeCompare(String(rightValue), 'id', { numeric: true, sensitivity: 'base' });

      return this.sortDirection === 'desc' ? -comparison : comparison;
    });
  },

  get totalPages() {
    return Math.max(1, Math.ceil(this.filteredRows.length / Number(this.perPage || 10)));
  },

  get pagedRows() {
    const currentPage = Math.min(this.page, this.totalPages);
    const start = (currentPage - 1) * Number(this.perPage || 10);

    return this.filteredRows.slice(start, start + Number(this.perPage || 10));
  },

  get rangeStart() {
    return this.filteredRows.length === 0 ? 0 : ((Math.min(this.page, this.totalPages) - 1) * Number(this.perPage || 10)) + 1;
  },

  get rangeEnd() {
    return Math.min(this.rangeStart + Number(this.perPage || 10) - 1, this.filteredRows.length);
  },

  get activeFilterCount() {
    return [
      this.resultSearch.trim() !== '',
      this.resultLabel !== 'all',
      this.resultSort !== 'row',
      this.sortDirection !== 'asc',
    ].filter(Boolean).length;
  },

  sortValue(row) {
    if (this.resultSort === 'label') return row.label || '';
    if (this.resultSort === 'confidence') return Number(row.confidence || 0);

    return Number.isFinite(Number(row.row)) ? Number(row.row) : (row.row || '');
  },

  resetPage() {
    this.page = 1;
  },

  resetResultFilters() {
    this.resultSearch = '';
    this.resultLabel = 'all';
    this.resultSort = 'row';
    this.sortDirection = 'asc';
    this.resetPage();
  },

  toggleSort(field) {
    if (this.resultSort === field) {
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      this.resultSort = field;
      this.sortDirection = field === 'confidence' ? 'desc' : 'asc';
    }

    this.resetPage();
  },

  openRowDetail(row) {
    this.rowDetail = row;
    this.rowDetailOpen = true;
  },

  chooseFile() {
    this.$refs.csvFile.click();
  },

  async setFile(files) {
    const file = files && files.length ? files[0] : null;

    if (!file) {
      return;
    }

    const lowerName = file.name.toLowerCase();
    const valid = lowerName.endsWith('.csv')
      || lowerName.endsWith('.txt')
      || lowerName.endsWith('.xlsx')
      || lowerName.endsWith('.xls');

    if (!valid) {
      this.fileName = '';
      this.uploadId = null;
      this.uploadMessage = '';
      this.errorMessage = 'File harus berformat CSV, TXT, XLSX, atau XLS';
      this.$refs.csvFile.value = '';
      return;
    }

    this.fileName = file.name;
    this.uploadId = null;
    this.uploadMessage = 'Menyimpan file ke database...';
    this.errorMessage = '';
    await this.uploadFile(file);
  },

  async uploadFile(file) {
    this.isUploading = true;

    const formData = new FormData();
    formData.append('file', file);

    try {
      const response = await fetch(this.uploadUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': this.csrfToken,
        },
        body: formData,
      });
      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        const firstFileError = data.errors && data.errors.file ? data.errors.file[0] : null;
        throw new Error(firstFileError || data.message || 'File gagal disimpan ke database');
      }

      this.uploadId = data.id;
      this.uploadMessage = data.message || 'File tersimpan di database.';
    } catch (error) {
      this.fileName = '';
      this.uploadId = null;
      this.uploadMessage = '';
      this.errorMessage = error.message || 'File gagal disimpan ke database';
      this.$refs.csvFile.value = '';
    } finally {
      this.isUploading = false;
    }
  },

  async dropFile(event) {
    this.isDragging = false;
    const files = event.dataTransfer.files;

    if (files.length) {
      const transfer = new DataTransfer();
      transfer.items.add(files[0]);
      this.$refs.csvFile.files = transfer.files;
    }

    await this.setFile(files);
  },
}));

Alpine.start();
