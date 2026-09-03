<?php 
// Arquivo de conexÆo com o banco de dados 
 
if (!function_exists('getConection')) { 
    function getConection() { 
        return getPDO(); 
    } 
} 
