import {
  ArchiveIcon,
  DatabaseIcon,
  FileTextIcon,
  FolderIcon,
  ImageIcon,
  LayoutTemplateIcon,
  PlugIcon,
  ScrollTextIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export interface Capability {
  id: string;
  label: string;
  icon: LucideIcon;
  /** The WordPress capability that gates this area for the connected user. */
  cap: string;
}

export const capabilities: Capability[] = [
  { id: 'content', label: 'Content', icon: FileTextIcon, cap: 'edit_posts' },
  { id: 'media', label: 'Media', icon: ImageIcon, cap: 'upload_files' },
  { id: 'elementor', label: 'Elementor', icon: LayoutTemplateIcon, cap: 'edit_pages' },
  { id: 'filesystem', label: 'Filesystem', icon: FolderIcon, cap: 'edit_files' },
  { id: 'database', label: 'Database (read-only)', icon: DatabaseIcon, cap: 'manage_options' },
  { id: 'extensions', label: 'Plugins & Themes', icon: PlugIcon, cap: 'activate_plugins' },
  { id: 'backups', label: 'Backups', icon: ArchiveIcon, cap: 'manage_options' },
  { id: 'audit', label: 'Audit Log', icon: ScrollTextIcon, cap: 'manage_options' },
];
