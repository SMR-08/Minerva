import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';

import { FormularioSubidaComponent } from './formulario-subida.component';

describe('FormularioSubidaComponent', () => {
  let component: FormularioSubidaComponent;
  let fixture: ComponentFixture<FormularioSubidaComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FormularioSubidaComponent],
      providers: [provideRouter([]), provideHttpClient()],
    })
    .compileComponents();

    fixture = TestBed.createComponent(FormularioSubidaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should handle COMPLETADO state as intermediate (waiting for summary)', () => {
    component.manejarEventoSSE({ estado: 'COMPLETADO' } as any);
    expect(component.cargando).toBeTrue();
    expect(component.mensaje).toContain('resumen');
  });

  it('should handle RESUMIENDO state', () => {
    component.manejarEventoSSE({ estado: 'RESUMIENDO' } as any);
    expect(component.cargando).toBeTrue();
    expect(component.mensaje).toContain('resumen');
  });

  it('should handle LISTO state as final', () => {
    component.manejarEventoSSE({ estado: 'LISTO' } as any);
    expect(component.cargando).toBeFalse();
    expect(component.progreso()).toBe(100);
  });

  it('should handle FALLIDO state', () => {
    component.manejarEventoSSE({ estado: 'FALLIDO', mensaje: 'GPU error' } as any);
    expect(component.cargando).toBeFalse();
    expect(component.error).toBeTrue();
  });
});
