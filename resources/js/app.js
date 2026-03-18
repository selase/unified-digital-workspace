import './bootstrap';

import Chart from 'chart.js/auto';

window.Chart = Chart;

import Alpine from 'alpinejs';
import tiptapEditor from './components/tiptap-editor.js';

window.Alpine = Alpine;

Alpine.data('tiptapEditor', tiptapEditor);

Alpine.start();
