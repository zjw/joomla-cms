-- Index and field changes to cater for UTF-8 Multibyte (utf8mb4)
ALTER TABLE `#__menu` DROP KEY `idx_client_id_parent_id_alias_language`, ADD KEY `idx_client_id_parent_id_alias_language` (`client_id`,`parent_id`,`alias`(191),`language`);
ALTER TABLE `#__redirect_links` DROP KEY `idx_link_old`, ADD KEY `idx_link_old` (`old_url`(191));
ALTER TABLE `#__menu` DROP  KEY `idx_path`, ADD KEY `idx_path` (`path`(191));
ALTER TABLE `#__session` MODIFY `session_id` varchar(191) NOT NULL DEFAULT '';
ALTER TABLE `#__user_keys` MODIFY `series` varchar(191) NOT NULL;
ALTER TABLE `#__update_sites_extensions` ENGINE=InnoDB;
ALTER TABLE `#__categories` DROP KEY `idx_alias`, ADD KEY `idx_alias` (`alias`(191));
ALTER TABLE `#__tags` DROP KEY `idx_alias`, ADD KEY `idx_alias` (`alias`(191));
ALTER TABLE `#__ucm_content` DROP KEY `idx_alias`, ADD KEY `idx_alias` (`core_alias`(191));
