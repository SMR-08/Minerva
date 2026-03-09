import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CarpetaDashboardComponent } from './carpeta-dashboard.component';

describe('CarpetaDashboardComponent', () => {
  let component: CarpetaDashboardComponent;
  let fixture: ComponentFixture<CarpetaDashboardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CarpetaDashboardComponent]
    })
    .compileComponents();
    
    fixture = TestBed.createComponent(CarpetaDashboardComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
