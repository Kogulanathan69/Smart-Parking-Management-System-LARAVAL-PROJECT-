import { checkIn } from './check-in';
import { checkOut } from './check-out';
import { sessionDetail, anprEventDetail, inspectionsPage, incidentForm, myShifts } from './ops-pages';

export function registerOps(Alpine) {
    Alpine.data('checkIn', checkIn);
    Alpine.data('checkOut', checkOut);
    Object.entries({ sessionDetail, anprEventDetail, inspectionsPage, incidentForm, myShifts }).forEach(([k, v]) => Alpine.data(k, v));
}
