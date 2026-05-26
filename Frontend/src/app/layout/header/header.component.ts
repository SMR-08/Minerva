import { Component, HostListener } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AuthService } from '../../auth.service';

@Component({
  selector: 'app-header',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, CommonModule],
  templateUrl: './header.component.html',
  styleUrl: './header.component.css'
})
export class HeaderComponent {
  menuOpen = false;

  constructor(public authService: AuthService) {}

  toggleMenu(): void {
    this.menuOpen = !this.menuOpen;
  }

  closeMenu(): void {
    this.menuOpen = false;
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent): void {
    const headerElement = document.querySelector('app-header');
    if (headerElement && !headerElement.contains(event.target as Node)) {
      this.menuOpen = false;
    }
  }

  logout(): void {
    this.closeMenu();
    this.authService.logout();
  }

  getUserEmail(): string {
    return this.authService.getUser()?.usuario ?? '';
  }

  getUserInitial(): string {
    const email = this.getUserEmail();
    return email ? email.charAt(0).toUpperCase() : '?';
  }
}
