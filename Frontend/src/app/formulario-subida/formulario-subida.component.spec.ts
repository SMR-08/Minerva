import { ComponentFixture, TestBed } from '@angular/core/testing';

import { FormularioSubidaComponent } from './formulario-subida.component';

describe('FormularioSubidaComponent', () => {
  let component: FormularioSubidaComponent;
  let fixture: ComponentFixture<FormularioSubidaComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FormularioSubidaComponent]
    })
    .compileComponents();
    
    fixture = TestBed.createComponent(FormularioSubidaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
