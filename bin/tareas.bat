@echo off
rem Tarea programada del Historial Medico (recordatorios y cola de correo).
rem Registrela una vez, como el usuario que ejecuta XAMPP:
rem   schtasks /Create /TN "Historial Medico - tareas" /SC MINUTE /MO 5 /TR "\"%~f0\"" /F
"C:\xampp\php\php.exe" "%~dp0tareas.php" >> "%~dp0..\storage\logs\tareas.log" 2>&1
