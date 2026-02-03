SELECT 
    os.id as servicio_id,
    os.id_servicio,
    s.nombre as servicio_nombre,
    os.cantidad,
    (SELECT COUNT(*) FROM ot_servicio_items WHERE ot_servicio_id = os.id) as items_count
FROM ot_servicios os
LEFT JOIN servicios s ON os.id_servicio = s.id
WHERE os.id_orden = 11;

SELECT * FROM ot_servicio_items WHERE ot_servicio_id IN (SELECT id FROM ot_servicios WHERE id_orden = 11);
