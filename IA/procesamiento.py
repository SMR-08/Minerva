"""
Módulo compartido para funciones de post-procesamiento.
Usado tanto por main.py como por worker.py
"""

def alinear_transcripcion(palabras: list, segmentos: list) -> list:
    """
    Alinea palabras (ASR) con segmentos (Diarizacion) por superposicion temporal.
    Esta version es 'fiel' al Diarizador, dejando la heuristica para pasos posteriores.
    """
    turnos_alineados = []
    turno_actual = None

    # Ordenar por seguridad
    palabras = sorted(palabras, key=lambda x: x["inicio"])
    segmentos = sorted(segmentos, key=lambda x: x["inicio"])

    for item_palabra in palabras:
        p_inicio = item_palabra["inicio"]
        p_fin = item_palabra["fin"]
        p_texto = item_palabra["palabra"]
        # Buscamos un punto de equilibrio (60%) para decidir el hablante
        p_eval = p_inicio + (p_fin - p_inicio) * 0.6

        hablante_detectado = "DESCONOCIDO"
        for seg in segmentos:
            if seg["inicio"] <= p_eval <= seg["fin"]:
                hablante_detectado = seg["hablante"]
                break

        # Agrupacion simple
        if turno_actual and turno_actual["hablante"] == hablante_detectado:
            turno_actual["texto"] += " " + p_texto
            turno_actual["fin"] = p_fin
        else:
            if turno_actual:
                turnos_alineados.append(turno_actual)
            turno_actual = {
                "hablante": hablante_detectado,
                "inicio": p_inicio,
                "fin": p_fin,
                "texto": p_texto
            }

    if turno_actual:
        turnos_alineados.append(turno_actual)

    return turnos_alineados


def suavizar_transcripcion(turnos: list) -> list:
    """
    Fusiona segmentos espurios.
    REGLA: Si B es muy CORTO (<1.0s) y empieza por minúscula, se considera error de diarización y se une a A.
    """
    if len(turnos) < 2:
        return turnos

    turnos_corregidos = []
    i = 0
    while i < len(turnos):
        actual = turnos[i].copy()

        while i + 1 < len(turnos):
            siguiente = turnos[i+1]
            texto_sig = siguiente["texto"].strip()
            # ¿Es una interrupción protegida? (Mayúscula y no es 'I')
            es_interrupcion_real = (texto_sig and texto_sig[0].isupper() and texto_sig != "I")
            duracion_sig = siguiente["fin"] - siguiente["inicio"]
            gap = siguiente["inicio"] - actual["fin"]

            # Unir si: mismo hablante O (es una falla corta en minúscula con gap pequeño)
            if actual["hablante"] == siguiente["hablante"]:
                actual["texto"] += " " + siguiente["texto"]
                actual["fin"] = siguiente["fin"]
                i += 1
            elif not es_interrupcion_real and duracion_sig < 1.0 and gap < 0.5:
                # Robo controlado: solo robamos segmentos insignificantes en minúscula
                actual["texto"] += " " + siguiente["texto"]
                actual["fin"] = siguiente["fin"]
                i += 1
            else:
                break

        turnos_corregidos.append(actual)
        i += 1

    return turnos_corregidos


def asignar_hablantes_desconocidos(turnos: list) -> list:
    """
    Asigna segmentos 'DESCONOCIDO' basandose en el contexto inmediato.
    Si un desconocido está entre el mismo hablante, se fusiona.
    Si está en un cambio de turno, se le asigna al que tiene más probabilidad por el texto (mayúsculas).
    """
    if len(turnos) < 2:
        return turnos

    hablantes_conocidos = set(t["hablante"] for t in turnos if t["hablante"] != "DESCONOCIDO")
    res = []

    for i, t in enumerate(turnos):
        if t["hablante"] == "DESCONOCIDO":
            previo = res[-1]["hablante"] if res else "DESCONOCIDO"
            siguiente = turnos[i+1]["hablante"] if i+1 < len(turnos) else "DESCONOCIDO"

            texto = t["texto"].strip()
            es_frontera = texto and texto[0].isupper() and texto != "I"

            if previo != "DESCONOCIDO" and previo == siguiente:
                 # Sandwich: A -> DESC -> A  => Probablemente es el interlocutor B o ruido de A
                 if not es_frontera:
                      t["hablante"] = previo
                 else:
                      # Si hay mayúscula, es probable que sea el "otro"
                      otros = hablantes_conocidos - {previo}
                      t["hablante"] = list(otros)[0] if otros else previo
            elif previo != "DESCONOCIDO":
                 t["hablante"] = previo if not es_frontera else (siguiente if siguiente != "DESCONOCIDO" else previo)
        res.append(t)

    # Consolidar de nuevo para evitar mini-fragmentos del mismo hablante tras la corrección
    if not res: return []
    final = [res[0]]
    for t in res[1:]:
        if t["hablante"] == final[-1]["hablante"]:
            final[-1]["texto"] += " " + t["texto"]
            final[-1]["fin"] = t["fin"]
        else:
            final.append(t)
    return final


def asignar_roles(turnos: list) -> list:
    """
    Asigna roles 'Profesor' y 'Alumnos'.
    Profesor = Hablante con mayor duracion total acumulada.
    """
    if not turnos:
        return []

    duracion_por_hablante = {}
    for t in turnos:
        h = t["hablante"]
        d = t["fin"] - t["inicio"]
        duracion_por_hablante[h] = duracion_por_hablante.get(h, 0) + d

    if not duracion_por_hablante:
        return turnos

    id_profesor = max(duracion_por_hablante, key=duracion_por_hablante.get)

    turnos_finales = []
    for t in turnos:
        nuevo_turno = t.copy()
        if t["hablante"] == id_profesor:
            nuevo_turno["hablante"] = "Profesor"
        else:
            # Cualquier otro es Alumnos (conjunto)
            nuevo_turno["hablante"] = "Alumnos"
        turnos_finales.append(nuevo_turno)

    return turnos_finales
