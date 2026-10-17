<h1>Verificar documento</h1>
<p class="lead">Escriba el código de verificación impreso al pie del documento para confirmar que fue emitido por el hospital.</p>
<form method="get" action="<?= e(url('verificar')) ?>" class="stack">
    <div class="field">
        <label for="codigo">Código de verificación</label>
        <input type="text" id="codigo" name="codigo" placeholder="XXXX-XXXX-XXXX" maxlength="14" autocomplete="off" spellcheck="false" required autofocus class="code-input">
        <p class="help">12 letras y números. Los guiones son opcionales.</p>
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= icon('verified', 16) ?> Verificar</button>
</form>
<p class="auth-links"><a href="<?= e(url('login')) ?>">Ir al inicio de sesión</a></p>
