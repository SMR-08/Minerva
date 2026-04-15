import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CommonModule } from '@angular/common';

interface University {
  name: string;
  logo: string;
  url: string;
}

interface Feature {
  title: string;
  desc: string;
  icon: string;
  bgColor: string;
}

@Component({
  selector: 'app-landing-page',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './landing-page.component.html',
  styleUrl: './landing-page.component.css'
})
export class LandingPageComponent {
  // 5 españolas + 5 extranjeras
  universities: University[] = [
    // Españolas
    { name: 'Universidad de Málaga', logo: 'assets/images/UMA.webp', url: 'https://www.uma.es' },
    { name: 'Universidad Complutense', logo: 'assets/images/UCM.png', url: 'https://www.ucm.es' },
    { name: 'Universidad de Córdoba', logo: 'assets/images/UCO.png', url: 'https://www.uco.es' },
    { name: 'Universidad de Barcelona', logo: 'assets/images/UB.jpg', url: 'https://www.ub.edu' },
    { name: 'Universidad de Salamanca', logo: 'assets/images/USAL.png', url: 'https://www.usal.es' },
    // Extranjeras
    { name: 'Harvard', logo: 'assets/images/harvard-university-logo.png', url: 'https://www.harvard.edu' },
    { name: 'Stanford', logo: 'assets/images/stanford-university-logo.png', url: 'https://www.stanford.edu' },
    { name: 'MIT', logo: 'assets/images/mit-logo-generic.png', url: 'https://www.mit.edu' },
    { name: 'Oxford', logo: 'assets/images/oxford-university-logo.jpg', url: 'https://www.ox.ac.uk' },
    { name: 'Cambridge', logo: 'assets/images/cambridge-university-logo.png', url: 'https://www.cam.ac.uk' },
  ];

  features: Feature[] = [
    {
      title: 'Transcripción Automática',
      desc: 'Convierte audio en texto con precisión excepcional.',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>',
      bgColor: 'rgba(74, 107, 138, 0.1)',
    },
    {
      title: 'Identificación de Hablantes',
      desc: 'Distingue automáticamente entre profesor y estudiantes.',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><circle cx="19" cy="7" r="3"/><path d="M21 21v-2a3 3 0 0 0-2-2.83"/></svg>',
      bgColor: 'rgba(212, 168, 67, 0.1)',
    },
    {
      title: 'Resúmenes con IA',
      desc: 'Genera resúmenes con los puntos clave de cada clase.',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
      bgColor: 'rgba(90, 138, 90, 0.1)',
    },
  ];
}
