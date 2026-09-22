import './checkout';
import Alpine from 'alpinejs';

/**
 * Livewire bundles and starts its own Alpine. Starting a second instance
 * breaks both (directives bind twice, stores diverge), so this bundle only
 * starts Alpine on pages Livewire is not present on — the static storefront
 * and print views, which still use x-data for tooltips and modals.
 */
if (! document.querySelector('script[src*="livewire"]')) {
    window.Alpine = Alpine;
    Alpine.start();
}
