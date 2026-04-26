import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { NotificacionComponent } from './notificacion/notificacion.component';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, NotificacionComponent],
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})
export class AppComponent {
  title = 'minervaProyecto';
}
