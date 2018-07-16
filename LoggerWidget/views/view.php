<div id="elasticlogBtn">
    <a  href="#" class="btn <?= ($log["time"] < 200) ? "btn-info" : "btn-danger" ?>" data-toggle="modal" data-target="#elasticlog"><span class="glyphicon glyphicon-scale"></span> Elasticsearch <b><?= $log["time"] ?> мс</b> </a>
</div>
<div class="modal fade" id="elasticlog" tabindex="-1" role="dialog" aria-labelledby="elasticlogModalLabel">
    <div class="modal-dialog" style="width:80%" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="elasticlogModalLabel">Elasticsearch количество запросов <?=$log["count"]?> за <?= ($log["time"] > 200) ? "<span class='danger'>{$log["time"]} мс</span>" : "<span class='success'>{$log["time"]} мс</span>" ?></h4>
            </div>
            <div class="modal-body" id="elasticlogBody">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr class="info">
                                <th>Время</th>
                                <th>Инфо о запросе</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $counter = 0;
                            foreach ($log["list"] as $_log) {
                                ?>
                                <tr>
                                    <td style="width:150px;" class="<?= ($_log["time"] < 1.0) ? "success" : "danger" ?>"><?= $_log["time"] ?> мс</td>
                                    <td>
                                        <a role="button" data-toggle="collapse" href="#collapseExample<?= $counter ?>" aria-expanded="false" aria-controls="collapseExample<?= $counter ?>">
                                            <?= json_encode($_log["caller"]) ?> : <?= json_encode($_log["query"]["index"]) ?>/<?= json_encode($_log["query"]["type"]) ?>
                                        </a>                                        
                                        <div class="collapse" id="collapseExample<?= $counter ?>">
                                            <div class="well">
                                                <?php foreach ($_log["trace"] as $call) { ?>
                                                <p><span style="color: #363636"><?= (isset($call['file'])) ? $call['file'] : '' ?>(<?= (isset($call['line'])) ? $call['line'] : '' ?>)</span> <b style="color: #3383bb"><?= (isset($call['class'])) ? $call['class'] : '' ?>::<?= $call['function'] ?></b></p>
                                                <?php } ?>
                                                <pre>
                                                    <?= json_encode($_log["query"], JSON_PRETTY_PRINT) ?>
                                                </pre>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                                <?php
                                $counter++;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
