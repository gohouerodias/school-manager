import './bootstrap';
import { initPasswordToggles } from './password-toggle';
import { initSlidePanels } from './slide-panel';
import { initActionMenus } from './action-menu';
import { initTableFilters } from './table-filter';
import { initFlashToast } from './flash-toast';
import { initAccountInvites } from './account-invites';
import { initAccountEdit } from './account-edit';
import { initProfileMenu } from './profile-menu';
import { initSidebar } from './sidebar';

document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
    initSlidePanels();
    initActionMenus();
    initTableFilters();
    initFlashToast();
    initAccountInvites();
    initAccountEdit();
    initProfileMenu();
    initSidebar();
});
