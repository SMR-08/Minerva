import { Routes } from '@angular/router';
import { LayoutComponent } from './layout/layout.component';
import { FormularioSubidaComponent } from './formulario-subida/formulario-subida.component';
import { FormularioRegistroComponent } from './formulario-registro/formulario-registro.component';
import { DashboardComponent } from './dashboard/dashboard.component';
import { FormularioLoginComponent } from './formulario-login/formulario-login.component';
import { LandingPageComponent } from './landing-page/landing-page.component';
import { AuthGuard } from './auth.guard';

export const routes: Routes = [
  {
    path: '',
    component: LandingPageComponent
  },
  {
    path: 'login',
    component: FormularioLoginComponent
  },
  {
    path: 'registro',
    component: FormularioRegistroComponent
  },
  {
    path: 'dashboard',
    component: LayoutComponent,
    canActivate: [AuthGuard],
    children: [
      {
        path: '',
        component: DashboardComponent
      },
      {
        path: 'subir',
        component: FormularioSubidaComponent
      },
      {
        path: 'cuenta',
        component: FormularioRegistroComponent
      }
    ]
  },
  {
    path: '**',
    redirectTo: '/',
    pathMatch: 'full'
  }
];
