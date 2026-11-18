# Scripts de la base de datos (modelo v15, propuesta)

Son los scripts oficiales de `base_de_datos_v15` (sin las consultas ni las pruebas SQL). En Workbench se ejecutan en este orden:

1. `01_crear_base_de_datos.sql`: borra y crea la base `segupyme_v15`, las 11 tablas y la vista `v_planilla_total`.
2. `02_restricciones_check.sql`: restricciones CHECK de Empresa y Usuario.
3. `03_funciones_y_procedimientos.sql`: funciones y procedimientos (permisos por rol, planillas, cargas masivas).
4. `04_triggers.sql`: los triggers que hacen cumplir las reglas.
5. `05_datos_de_prueba.sql`: catalogo de conceptos, valores legales y el ejemplo de Textiles Andina y Panaderia El Trigal.
6. `08_seguridad.sql`: las cuentas `segupyme_app`, `segupyme_lectura` y `segupyme_migraciones` y sus permisos.

Laravel NO usa migraciones para estas tablas. La aplicacion se conecta con la cuenta `segupyme_app`: puede leer todo y escribir
solo `Usuario`, `Empresa` y `Cargos`; todo lo demas (novedades, afiliaciones, planillas, valores legales y bitacora) se escribe
llamando procedimientos. Las pruebas de Laravel reconstruyen una base aparte (`segupyme_v15_test`) con estos mismos archivos.
