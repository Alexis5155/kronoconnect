<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= e($subject ?? '') ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .header { text-align: center; border-bottom: 1px solid #eeeeee; padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #333333; font-size: 24px; }
        .content { color: #555555; line-height: 1.6; font-size: 16px; }
        .footer { text-align: center; color: #999999; font-size: 12px; margin-top: 30px; border-top: 1px solid #eeeeee; padding-top: 20px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #3B82F6; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= e($appName ?? 'KronoConnect') ?></h1>
        </div>
        <div class="content">
            <?= $content ?>
        </div>
        <div class="footer">
            Cet e-mail est généré automatiquement par <?= e($appName ?? 'KronoConnect') ?>. Merci de ne pas y répondre.<br>
            <?= e($collectivite ?? '') ?>
        </div>
    </div>
</body>
</html>