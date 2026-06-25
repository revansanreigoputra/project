import axios from 'axios';
window.axios = axios;

// Header default supaya request AJAX dikenali Laravel sebagai XMLHttpRequest.
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
