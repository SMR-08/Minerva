import { TestBed } from '@angular/core/testing';
import { BrowserModule, DomSanitizer } from '@angular/platform-browser';
import { MarkdownPipe } from './markdown.pipe';

describe('MarkdownPipe', () => {
  let pipe: MarkdownPipe;
  let sanitizer: DomSanitizer;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [BrowserModule],
    });
    sanitizer = TestBed.inject(DomSanitizer);
    pipe = new MarkdownPipe(sanitizer);
  });

  it('should create', () => {
    expect(pipe).toBeTruthy();
  });

  it('should return empty string for null', () => {
    expect(pipe.transform(null)).toBe('');
  });

  it('should return empty string for undefined', () => {
    expect(pipe.transform(undefined)).toBe('');
  });

  it('should convert ## headers to h3', () => {
    const result = pipe.transform('## Tema principal') as any;
    expect(result.changingThisBreaksApplicationSecurity).toContain('<h3>Tema principal</h3>');
  });

  it('should convert ### headers to h4', () => {
    const result = pipe.transform('### Puntos clave') as any;
    expect(result.changingThisBreaksApplicationSecurity).toContain('<h4>Puntos clave</h4>');
  });

  it('should convert list items', () => {
    const result = pipe.transform('- Punto uno\n- Punto dos') as any;
    expect(result.changingThisBreaksApplicationSecurity).toContain('<li>Punto uno</li>');
    expect(result.changingThisBreaksApplicationSecurity).toContain('<ul>');
  });

  it('should convert bold text', () => {
    const result = pipe.transform('Esto es **importante**') as any;
    expect(result.changingThisBreaksApplicationSecurity).toContain('<strong>importante</strong>');
  });
});
