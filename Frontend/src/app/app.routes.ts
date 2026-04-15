import { Routes } from '@angular/router';
import { LayoutComponent } from './layout/layout.component';
import { FormularioSubidaComponent } from './formulario-subida/formulario-subida.component';
import { FormularioRegistroComponent } from './formulario-registro/formulario-registro.component';
import { DashboardComponent } from './dashboard/dashboard.component';
import { AsignaturaViewComponent } from './asignatura-view/asignatura-view.component';
import { TranscripcionViewComponent } from './transcripcion-view/transcripcion-view.component';
import { FormularioLoginComponent } from './formulario-login/formulario-login.component';
import { LandingPageComponent } from './landing-page/landing-page.component';
import { AuthGuard } from './auth.guard';

export const routes: Routes = [
  { path: '', component: LandingPageComponent },
  { path: 'login', component: FormularioLoginComponent },
  { path: 'registro', component: FormularioRegistroComponent },
  { path: 'dashboard', component: DashboardComponent, canActivate: [AuthGuard] },
  { path: 'asignatura/:id', component: AsignaturaViewComponent, canActivate: [AuthGuard] },
  { path: 'transcripcion/:id', component: TranscripcionViewComponent, canActivate: [AuthGuard] },
  { path: 'subir', component: FormularioSubidaComponent, canActivate: [AuthGuard] },
  { path: '**', redirectTo: '/', pathMatch: 'full' }
];
