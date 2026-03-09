import { Routes } from '@angular/router';
import { LayoutComponent } from './layout/layout.component';
import { FormularioSubidaComponent } from './formulario-subida/formulario-subida.component';
import { FormularioRegistroComponent } from './formulario-registro/formulario-registro.component';
import { DashboardComponent } from './dashboard/dashboard.component';
import { FormularioLoginComponent } from './formulario-login/formulario-login.component';
import { authGuard } from './auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    component: FormularioLoginComponent
  },
  {
    path: 'registro',
    component: FormularioRegistroComponent
  },
  {
    path: '',
    component: LayoutComponent,
    canActivate: [authGuard],
    children: [
      {
        path: 'dashboard',
        component: DashboardComponent
      },
      {
        path: 'subir',
        component: FormularioSubidaComponent
      },
      {
        path: '',
        redirectTo: 'dashboard',
        pathMatch: 'full'
      }
    ]
  }
];
