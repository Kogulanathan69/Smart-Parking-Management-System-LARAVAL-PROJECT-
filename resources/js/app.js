import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { api, uuid, ApiError } from './lib/api';
import * as fmt from './lib/format';
import { registerStores, toast, confirmAction, reportError } from './lib/ui';
import { dataTable, form, crudPage } from './components/table';
import { registerShell } from './pages/shell';
import { registerDashboards } from './pages/dashboards';
import { registerOps } from './pages/ops';
import { registerManage } from './pages/manage';
import { registerPortal } from './pages/portal';

// Small, explicit global surface for inline Alpine expressions. No secrets live here.
window.ParkFlow = { api, uuid, fmt, toast, confirm: confirmAction, reportError, ApiError };
window.Alpine = Alpine;

Alpine.plugin(focus);
Alpine.magic('fmt', () => fmt);
registerStores(Alpine);
Alpine.data('dataTable', dataTable);
Alpine.data('form', form);
Alpine.data('crudPage', crudPage);
registerShell(Alpine);
registerDashboards(Alpine);
registerOps(Alpine);
registerManage(Alpine);
registerPortal(Alpine);

Alpine.start();
