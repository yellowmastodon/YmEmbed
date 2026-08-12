import { init as initEmbeds} from './lazyframe-core.js';

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initEmbeds);
} else {
  initEmbeds();
}
